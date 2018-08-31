<?php
/**
 * @author OnTheGo Systems
 */
namespace OTGS\Connectors;

interface IssuesTrackerChangelog
{
    /**
     * @param      $project_id
     * @param null $fix_version
     *
     * @return array
     */
    public function getChangelogEntries($project_id, $fix_version = null);

    /**
     * @param $projectId
     *
     * @return array
     */
    public function getProjectFixVersions($projectId);

    /**
     * @param string $issue_id
     * @param string $message
     *
     * @return bool
     */
    public function updateChangelog($issue_id, $message);
}
