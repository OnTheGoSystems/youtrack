<?php
/**
 * @author OnTheGo Systems
 */

namespace OTGS\YouTrack;

use OTGS\Connectors\YouTrack\YouTrackConnection;
use OTGS\YouTrack\Commands\CommandBase;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

class TicketsResolutionCommand extends CommandBase {
	const OTGS_YT_FILTER                = 'OTGS_YT_ISSUES_FILTER';
	const OTGS_YT_ISSUES_LIMIT          = 'OTGS_YT_ISSUES_LIMIT';
	const OTGS_YT_ISSUES_ASSIGNEE_LIMIT = 'OTGS_YT_ISSUES_ASSIGNEE_LIMIT';
	const OTGS_YT_OUTPUT_FILE           = 'OTGS_YT_OUTPUT_FILE';
	const OTGS_YT_OVERWRITE_OUTPUT_FILE = 'OTGS_YT_OVERWRITE_OUTPUT_FILE';
	const OTGS_YT_TOKEN                 = 'OTGS_YT_TOKEN';
	const OTGS_YT_URL                   = 'OTGS_YT_URL';

	const MINUTES_IN_HOUR   = 60;
	const HOURS_IN_WORK_DAY = 8;

	private $progress;
	private $issues_history_cache = array();
	private $ytConnection;

	protected function initOptionsCommand() {
	}

	protected function configureCommand() {
		$this->setName(
			'reports'
		)
			 ->setDescription(
				 'Receive data from the Issues Tracker.'
			 )
			 ->setHelp(
				 'This command allows you to get the Issue Tracker data.'
			 )
			 ->setDefinition(
				 new InputDefinition(
					 array(
						 new InputOption(
							 'yt-url',
							 null,
							 $this->optionRequired( self::OTGS_YT_URL ),
							 'URL of the YouTrack instance.'
						 ),
						 new InputOption(
							 'yt-token',
							 null,
							 $this->optionRequired( self::OTGS_YT_TOKEN ),
							 'YouTrack token.'
						 ),
						 new InputOption(
							 'filter',
							 null,
							 $this->optionRequired( self::OTGS_YT_FILTER ),
							 'The query.'
						 ),
						 new InputOption(
							 'limit',
							 null,
							 InputOption::VALUE_OPTIONAL,
							 'Limit results to the given number.',
							 1000
						 ),
						 new InputOption(
							 'limit-per-assignee',
							 null,
							 InputOption::VALUE_OPTIONAL,
							 'Limit results per assignee to the given number.'
						 ),
						 new InputOption(
							 'output-file',
							 null,
							 InputOption::VALUE_OPTIONAL,
							 'Output the results to the provided file.'
						 ),
						 new InputOption(
							 'overwrite-file',
							 null,
							 InputOption::VALUE_OPTIONAL,
							 'Overwrite the output file if specified (default is true).',
							 'yes'
						 ),
					 )
				 )
			 );

	}

	private function optionRequired( $envVariableName ) {
		if ( $this->hasEnvVariable( $envVariableName ) ) {
			return InputOption::VALUE_OPTIONAL;
		}

		return InputOption::VALUE_REQUIRED;
	}

	protected function executeCommand() {
		$records = $this->getTickets();

		if ( ! $this->getOutputFile() ) {
			$this->output->writeln( json_encode( $records, JSON_PRETTY_PRINT ) );
		}
	}

	/**
	 * @return array
	 */
	private function getTickets() {
		$assignee_count = array();
		$this->output->writeln( 'Getting data from Issues Tracker using <info>' . $this->getFilter() . '</info> ...' );

		$issues = $this->getYouTrackConnection()->getIssuesByFilter( $this->getFilter(), $this->getLimit() );

		$issues_count = count( $issues );
		$this->output->writeln( 'Found ' . $issues_count . ' tickets.' );

		if ( ! $issues_count ) {
			return $issues;
		}

		$this->initFile();

		$progressTemplate = ' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %message:6s%';
		ProgressBar::setFormatDefinition( 'custom', $progressTemplate );

		$this->progress = new ProgressBar( $this->output, $issues_count );
		$this->progress->setFormat( 'custom' );
		$this->progress->setMessage( 'Processing tickets(s)' );
		$this->progress->start();

		$report_items = array();

		/** @var \YouTrack\Issue $issue */
		foreach ( $issues as $issue ) {
			$assignee = 'Unassigned';
			if ( $issue->hasAssignee() ) {
				$assignee = $issue->getAssignee()->login;
			}

			if ( ! array_key_exists( $assignee, $assignee_count ) ) {
				$assignee_count[ $assignee ] = 0;
			}

			$assignee_count[ $assignee ] += 1;

			if ( $this->getLimitPerAssignee() && $assignee_count[ $assignee ] == $this->getLimitPerAssignee() ) {
				$this->progress->advance();
				continue;
			}
			$this->progress->setMessage( 'Reading <info>' . $issue->getId() . '</info>' );

			$report_item    = array(
				'ID'         => $issue->getId(),
				'Title'      => '"' . str_replace( '"', '""', $issue->getSummary() ) . '"',
				'URL'        => $this->getIssueURL( $issue ),
				'Assignee'   => '"' . $assignee . '"',
				'Created'    => $this->getDateFormat( $issue->getCreated() ),
				'Started'    => $this->getDateFormat( $this->getStartDate( $issue ) ),
				'Estimation' => $this->getEstimationInDays( $issue ),
				'Resolved'   => $this->getDateFormat( $issue->getResolved() ),
			);
			$report_items[] = $report_item;
			$this->updateFile( $report_item );
			$this->clearIssueHistoryCache( $issue );

			$this->progress->advance();
		}
		$this->progress->finish();

		return $issues;
	}

	private function getYouTrackConnection() {
		if ( ! $this->ytConnection ) {
			$this->ytConnection = new YouTrackConnection( $this->getYTLogin() );
		}

		return $this->ytConnection;
	}

	private function getIssueURL( \YouTrack\Issue $issue ) {
		return $this->getYouTrackUrl() . '/issue/' . $issue->getId();
	}

	/**
	 * @param int $time
	 *
	 * @return false|string
	 */
	private function getDateFormat( $time ) {
		return date( 'Y-m-d', substr( $time, 0, 10 ) );
	}

	private function getStartDate( \YouTrack\Issue $issue ) {
		$startDate = $issue->getCreated();

		$historyItem = $this->getHistoryItem( $issue, 'State', 'In Progress' );
		if ( $historyItem ) {
			return $historyItem['updated'];
		}

		/**
		 * Use "Start Date" only if we can't find the date when the state changed to "In Progress" (more reliable)
		 */
		if ( $issue->__get( 'Start Date' ) ) {
			return $issue->__get( 'Start Date' );
		}

		if ( $issue->hasAssignee() ) {
			$historyItem = $this->getHistoryItem( $issue, 'State', 'Open' );
			if ( $historyItem && $historyItem['updaterName'] === $issue->getAssignee()->login ) {
				return $historyItem['updated'];
			}
			$historyItem = $this->getHistoryItem( $issue, 'Assignee', $issue->getAssignee()->login );
			if ( $historyItem && $historyItem['updaterName'] === $issue->getAssignee()->login ) {
				return $historyItem['updated'];
			}
		}

		return $startDate;
	}

	private function getHistoryItem( \YouTrack\Issue $issue, $field, $value ) {
		if ( ! array_key_exists( $issue->getId(), $this->issues_history_cache ) ) {
			$history = $issue->getHistory();
			if ( $history ) {
				$this->issues_history_cache[ $issue->getId() ] = $history;
			}
		}

		foreach ( $this->issues_history_cache[ $issue->getId() ] as $change ) {
			if ( array_key_exists( $field, $change ) && $change[ $field ] == $value ) {
				return $change;
			}
		}

		return null;
	}

	private function clearIssueHistoryCache( \YouTrack\Issue $issue ) {
		unset( $this->issues_history_cache[ $issue->getId() ] );
	}

	private function initFile() {
		if ( $this->getOutputFile() && $this->getOverwriteFile() ) {
			$this->stream->put( $this->getOutputFile(), '' );
		}
	}

	private function updateFile( array $report_item ) {
		if ( ! $this->getOutputFile() ) {
			return;
		}

		$file = $this->stream->get( $this->getOutputFile() );

		if ( ! $file ) {
			$this->stream->put( $this->getOutputFile(), implode( ',', array_keys( $report_item ) ), FILE_APPEND );
		}

		$this->stream->put( $this->getOutputFile(), PHP_EOL . implode( ',', $report_item ), FILE_APPEND );
	}

	private function getEstimationInDays( \YouTrack\Issue $issue ) {
		$estimation = $issue->__get( 'Estimation' );
		if ( $estimation ) {
			return $estimation/self::MINUTES_IN_HOUR/self::HOURS_IN_WORK_DAY;
		}

		return 0;
	}

	/**
	 * @param $value
	 *
	 * @return bool
	 */
	private function castToBoolean( $value ) {
		if ( $value ) {

			if ( is_string( $value ) ) {
				if ( is_numeric( $value ) ) {
					return (bool) (int) $value;
				}

				return strtolower( substr( $value, 0, 1 ) ) === 'y';
			}

			return (bool) $value;
		}

		return false;
	}

	/**
	 * @return string|null
	 */
	protected function getFilter() {
		return $this->getOption( self::OTGS_YT_FILTER, 'filter' );
	}

	/**
	 * @return int|null
	 */
	protected function getLimit() {
		return $this->getOption( self::OTGS_YT_ISSUES_LIMIT, 'limit' );
	}

	/**
	 * @return int|null
	 */
	protected function getLimitPerAssignee() {
		return $this->getOption( self::OTGS_YT_ISSUES_ASSIGNEE_LIMIT, 'limit-per-assignee' );
	}

	/**
	 * @return string|null
	 */
	protected function getOutputFile() {
		return $this->getOption( self::OTGS_YT_OUTPUT_FILE, 'output-file' );
	}

	/**
	 * @return bool
	 */
	protected function getOverwriteFile() {
		return $this->castToBoolean( $this->getOption( self::OTGS_YT_OVERWRITE_OUTPUT_FILE, 'overwrite-file' ) );
	}

	/**
	 * @return string|null
	 */
	protected function getYouTrackToken() {
		return $this->getOption( self::OTGS_YT_TOKEN, 'yt-token' );
	}

	/**
	 * @return string|null
	 */
	protected function getYouTrackUrl() {
		return $this->getOption( self::OTGS_YT_URL, 'yt-url' );
	}

	private function getOption( $envVariableName, $optionName ) {
		if ( $this->hasEnvVariable( $envVariableName ) ) {
			return getenv( $envVariableName );
		}

		return $this->input->getOption( $optionName );
	}

	private function hasEnvVariable( $envVariableName ) {
		return (bool) getenv( $envVariableName );
	}

	/**
	 * @return array
	 */
	private function getYTLogin() {
		return array(
			'token' => $this->getYouTrackToken(),
			'url'   => $this->getYouTrackUrl(),
		);
	}
}