<?php
/**
 * @author OnTheGo Systems
 */

namespace OTGS\Connectors\YouTrack;

use OTGS\Connectors\IssuesTrackerChangelog;
use OTGS\Connectors\IssuesTrackerConnection;
use YouTrack\CustomField;
use YouTrack\Issue;
use YouTrack\Link;

class YouTrackChangelog implements IssuesTrackerChangelog
{
    const CHANGELOG_ENTRY_FIELD = 'Changelog entry';
    const FIX_VERSIONS_FIELD    = 'Fix versions';
    const TYPE_ENTRY_FIELD      = 'Type';
    const EXCLUDE_ENTRIES_TAG   = 'not-released';
    /**
     * @var IssuesTrackerConnection
     */
    private $issuesTrackerConnection;
    private $project_fix_versions = array();

    /**
     * Changelog constructor.
     *
     * @param IssuesTrackerConnection|YouTrackConnection $issuesTrackerConnection
     */
    public function __construct(YouTrackConnection $issuesTrackerConnection)
    {
        $this->issuesTrackerConnection = $issuesTrackerConnection;
        $this->issuesTrackerConnection->login();
    }

    /**
     * @param string $issue_id
     * @param string $message
     *
     * @return bool
     */
    public function updateChangelog($issue_id, $message)
    {
        $issue = $this->issuesTrackerConnection->getIssue($issue_id);
        if ($issue) {
            $project_id = $issue->getProjectShortName();

            try {
                $project_field = $this->issuesTrackerConnection->getProjectField(
                    $project_id,
                    self::CHANGELOG_ENTRY_FIELD
                );

                if ($project_field) {
                    return $this->issuesTrackerConnection->updateIssueField(
                        $issue_id,
                        self::CHANGELOG_ENTRY_FIELD,
                        $message
                    );
                }
            } catch (\Exception $ex) {
                return false;
            }
        }

        return false;
    }

    /**
     * @param      $project_id
     * @param null $fix_version
     *
     * @return array
     */
    public function getChangelogEntries($project_id, $fix_version = null)
    {
        $entries = array();

        $query = $this->getChangelogEntriesQuery($project_id, $fix_version);

        $issues = $this->issuesTrackerConnection->getIssuesByFilter($query);

        /** @var Issue $issue */
        foreach ($issues as $issue) {
            /** @noinspection ImplicitMagicMethodCallInspection */
            $fixVersions     = $issue->__get(self::FIX_VERSIONS_FIELD);
            $parentWithEntry = $this->getParentWithChangelogEntry($issue, $fixVersions);

            if ($parentWithEntry) {
                $entries = $this->addEntry($entries, $parentWithEntry);
            }
            $entries = $this->addEntry($entries, $issue);
        }

        return $entries;
    }

    private function getParentWithChangelogEntry(Issue $issue, $fixVersions)
    {
        if ($issue->hasLinks()) {
            $links = $issue->getLinks();

            /** @var Link $link */
            foreach ($links as $link) {
                if ($link->getTypeName() === 'Subtask' && $link->getTarget() === $issue->getId()) {
                    $parent = $this->issuesTrackerConnection->getIssue($link->getSource());
                    /** @noinspection ImplicitMagicMethodCallInspection */
                    $parentFixVersions = $issue->__get(self::FIX_VERSIONS_FIELD);
                    /** @noinspection ImplicitMagicMethodCallInspection */
                    $parentChangelogEntry = $parent->__get(self::CHANGELOG_ENTRY_FIELD);

                    if ($parentFixVersions === $fixVersions) {
                        if ($parentChangelogEntry) {
                            return $parent;
                        }

                        $grandParent = $this->getParentWithChangelogEntry($parent, $fixVersions);
                        if ($grandParent) {
                            return $grandParent;
                        }
                    }
                }
            }
        }

        return null;
    }

    private function getParent(Issue $issue, $fixVersions)
    {
        if ($issue->hasLinks()) {
            $links = $issue->getLinks();
            /** @var Link $link */
            foreach ($links as $link) {
                /** @noinspection ImplicitMagicMethodCallInspection */
                $parentFixVersions = $issue->__get(self::FIX_VERSIONS_FIELD);
                $matchFixVersion   = $parentFixVersions === $fixVersions;
                if ($matchFixVersion && $this->isSubTaskOf($link, $issue)) {
                    return $this->issuesTrackerConnection->getIssue($link->getSource());
                }
            }
        }

        return null;
    }

    private function isSubTaskOf(Link $link, Issue $issue)
    {
        return $link->getTypeName() === 'Subtask' && $link->getTarget() === $issue->getId();
    }

    private function addEntry($entries, Issue $issue)
    {
        if (! array_key_exists($issue->getId(), $entries)) {
            /** @noinspection ImplicitMagicMethodCallInspection */
            $fixVersions = $issue->__get(self::FIX_VERSIONS_FIELD);
            /** @noinspection ImplicitMagicMethodCallInspection */
            $changelogEntry = $issue->__get(self::CHANGELOG_ENTRY_FIELD);

            $parent = $this->getParent($issue, $fixVersions);

            if ($parent) {
                /** @noinspection ImplicitMagicMethodCallInspection */
                $entryType = $parent->__get(self::TYPE_ENTRY_FIELD);
            } else {
                /** @noinspection ImplicitMagicMethodCallInspection */
                $entryType = $issue->__get(self::TYPE_ENTRY_FIELD);
            }

            if ($fixVersions && trim($changelogEntry)) {
                $entries[$issue->getId()] = array(
                    'type'    => $entryType,
                    'message' => $changelogEntry,
                );
            }
        }

        return $entries;
    }

	/**
	 * @param $project_id
	 *
	 * @return array
	 * @throws \Exception
	 */
    public function getProjectFixVersions(
        $project_id
    ) {
        if (array_key_exists($project_id, $this->project_fix_versions)) {
            return $this->project_fix_versions[$project_id];
        }
        $versions = array();

        /** @var CustomField $field */
        $bundleName = $this->issuesTrackerConnection->getProjectFieldValue($project_id, self::FIX_VERSIONS_FIELD);
        $fieldType  = $this->issuesTrackerConnection->getProjectFieldType($project_id, self::FIX_VERSIONS_FIELD);
        if ($bundleName) {
            $versions = $this->issuesTrackerConnection->getBundleValues($fieldType, $bundleName);
        }

        $this->project_fix_versions[$project_id] = $versions;

        return $versions;
    }

    /**
     * @param $project_id
     * @param $fix_version
     *
     * @return string
     */
    private function getChangelogEntriesQuery(
        $project_id,
        $fix_version
    ) {
        $query = 'project: ' . $project_id;
        $query .= ' Changelog entry: -{no entry}, -{No changelog entry}';
        if (! $fix_version) {
            $query .= ' Fix versions: -Unscheduled, -Next, -Never, -Future';
        } else {
            $query .= ' Fix versions: ' . $fix_version;
        }
        $query .= ' State: Resolved';
        $query .= ' Tag: -{' . self::EXCLUDE_ENTRIES_TAG . "}";

        return $query;
    }
}
