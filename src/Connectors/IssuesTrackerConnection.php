<?php
/**
 * @author OnTheGo Systems
 */
namespace OTGS\Connectors;

interface IssuesTrackerConnection
{
    /**
     * @param string $field_type
     * @param string $bundle_name
     *
     * @return mixed
     */
    public function getBundle($field_type, $bundle_name);

    /**
     * @param string $field_type
     * @param string $bundle_name
     *
     * @return mixed
     */
    public function getBundleValues($field_type, $bundle_name);

    /**
     * @param int|string $issue_id
     *
     * @return mixed
     */
    public function getIssue($issue_id);

    /**
     * @param $filter
     *
     * @return array
     */
    public function getIssuesByFilter($filter);

    /**
     * @param int|string $project_id
     * @param string     $field_name
     *
     * @return \YouTrack\CustomField
     */
    public function getProjectField($project_id, $field_name);

    /**
     * @param int|string $project_id
     * @param string     $field_name
     *
     * @return string
     */
    public function getProjectFieldType($project_id, $field_name);

    /**
     * @param int|string $project_id
     * @param string     $field_name
     *
     * @return mixed
     */
    public function getProjectFieldValue($project_id, $field_name);

    /**
     * @param int|string $issue_id
     * @param int|string $field
     * @param mixed      $value
     *
     * @return bool
     */
    public function updateIssueField($issue_id, $field, $value);
}
