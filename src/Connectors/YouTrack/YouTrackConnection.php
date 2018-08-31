<?php
/**
 * @author OnTheGo Systems
 */

namespace OTGS\Connectors\YouTrack;

use OTGS\Connectors\IssuesTrackerConnection;
use YouTrack\Connection;
use YouTrack\Exception;
use YouTrack\Issue;

class YouTrackConnection implements IssuesTrackerConnection {
	private $youtrack_url;
	private $token;
	private $password;
	private $username;

	/** @var Connection */
	private $youtrack;

	/**
	 * Api constructor.
	 *
	 * @param array $login_data
	 */
	public function __construct( $login_data ) {
		$this->youtrack_url = $login_data['url'];
		if ( array_key_exists( 'token', $login_data ) ) {
			$this->token = $login_data['token'];
		} else {
			$this->username = $login_data['username'];
			$this->password = $login_data['password'];
		}
	}

	/**
	 * @param string $filter
	 * @param int    $limit
	 *
	 * @return array
	 */
	public function getIssuesByFilter( $filter, $limit = 1000 ) {
		$this->checkConnection();

		return $this->youtrack->getIssuesByFilter( $filter, null, $limit );
	}

	/**
	 * @param int|string $issue_id
	 *
	 * @return Issue
	 */
	public function getIssue( $issue_id ) {
		$this->checkConnection();

		return $this->youtrack->getIssue( $issue_id );
	}

	/**
	 * @param int|string $issue_id
	 * @param int|string $field
	 * @param mixed      $value
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function updateIssueField( $issue_id, $field, $value ) {
		$command = $field . ' ' . str_replace( '"', '\"', $value );
		try {
			return $this->youtrack->executeCommand(
				$issue_id,
				$command,
				null,
				null,
				true
			);
		} catch ( Exception $ex ) {
			return null;
		}
	}

	/**
	 * @param $project_id
	 * @param $field_name
	 *
	 * @return mixed
	 */
	public function getProjectField( $project_id, $field_name ) {
		return $this->youtrack->getProjectCustomField( $project_id, $field_name );
	}

	/**
	 * @param $project_id
	 * @param $field_name
	 *
	 * @return mixed
	 */
	public function getProjectFieldValue( $project_id, $field_name ) {
		$field = $this->getProjectField( $project_id, $field_name );

		/** @noinspection ImplicitMagicMethodCallInspection */
		return $field->__get( 'value' );
	}

	/**
	 * @param $projectId
	 * @param $fieldName
	 *
	 * @return string
	 */
	public function getProjectFieldType( $projectId, $fieldName ) {
		$field = $this->getProjectField( $projectId, $fieldName );

		return $field->getType();
	}

	/**
	 * @param $field_type
	 * @param $bundle_name
	 *
	 * @return \YouTrack\Bundle
	 * @throws \Exception
	 */
	public function getBundle( $field_type, $bundle_name ) {
		return $this->youtrack->getBundle( $field_type, $bundle_name );
	}

	/**
	 * @param $field_type
	 * @param $bundle_name
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function getBundleValues( $field_type, $bundle_name ) {
		$values = array();
		$bundle = $this->getBundle( $field_type, $bundle_name );

		foreach ( $bundle->getValues() as $value ) {
			if ( version_compare( $value->getName(), '0.0.1', '>=' ) ) {
				$values[] = $value->getName();
			}
		}

		return $values;
	}

	/**
	 * @return Connection
	 */
	private function checkConnection() {
		if ( ! $this->youtrack ) {
			$this->login();
		}

		return $this->youtrack;
	}

	public function login() {
		if ( $this->token ) {
			$this->youtrack = new Connection( $this->youtrack_url, $this->token, null );
		} else {
			$this->youtrack = new Connection( $this->youtrack_url, $this->username, $this->password );
		}
	}
}
