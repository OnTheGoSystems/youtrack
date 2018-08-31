<?php
/**
 * @author OnTheGo Systems
 */

namespace OTGS\YouTrack\Commands;

use OTGS\Stream;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

abstract class CommandBase extends Command
{
    protected $changelog_file_template;
    protected $changelog_target_path;
    /** @var Stream */
    protected $stream;
    /**
     * @var InputInterface
     */
    protected $input;
    /**
     * @var OutputInterface
     */
    protected $output;

    protected $requires_youtrack = true;
    protected $dry_run = false;
    protected $synchronize;
    protected $youtrack_login_data;
    protected $json_settings;

    /**
     * CommandBase constructor.
     *
     * @param Stream $stream
     *
     * @throws \Symfony\Component\Console\Exception\LogicException
     */
    public function __construct(Stream $stream)
    {
        parent::__construct();
        $this->stream = $stream;
    }

    abstract protected function initOptionsCommand();

    abstract protected function configureCommand();

    abstract protected function executeCommand();

    protected function ignoreYouTrack()
    {
        $this->requires_youtrack = false;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input  = $input;
        $this->output = $output;

        $this->initOptions();

        $this->executeCommand();
    }

    protected function initOptions()
    {
        $this->checkAllRequiredOptionsAreNotEmpty();

        if ($this->input->hasOption('dry-run')) {
            $this->dry_run = (bool)$this->input->getOption('dry-run');
        }

        $this->changelog_target_path   = realpath(
            $this->getChangelogSetting('path')
        );
        $this->changelog_file_template = $this->getChangelogSetting('fileTemplate');

        $this->initOptionsCommand();
    }

    protected function configure()
    {
        $this->configureCommand();
        $definitions = $this->getDefinition();
        $definitions->addOption(
            new InputOption(
                'dry-run', null, InputOption::VALUE_NONE, 'Executes a dry run'
            )
        );

        if ($this->requires_youtrack) {
            $definitions->addOptions(
                array(
                    new InputOption(
                        'youtrack-url', null, InputOption::VALUE_OPTIONAL
                    ),
                    new InputOption(
                        'youtrack-username', null, InputOption::VALUE_OPTIONAL
                    ),
                    new InputOption(
                        'youtrack-password', null, InputOption::VALUE_OPTIONAL
                    ),
                )
            );
        }
    }

    protected function checkAllRequiredOptionsAreNotEmpty()
    {
        $options = $this->getDefinition()->getOptions();
        foreach ($options as $option) {
            $name  = $option->getName();
            $value = $this->input->getOption($name);
            if (! $value && $option->isValueRequired()) {
                throw new InvalidArgumentException(sprintf('The required option %s is not set', $name));
            }
        }
    }

    protected function getChangelogSetting($name)
    {
        if ($this->input->hasOption('filename-template') && $this->input->getOption('filename-template')) {
            return $this->input->getOption('filename-template');
        }

        return $this->getJSONSetting('changelog', $name);
    }

    protected function getYouTrackArgument($name)
    {
        if ($this->input->hasOption('youtrack-' . $name) && $this->input->getOption('youtrack-' . $name)) {
            return $this->input->getOption('youtrack-' . $name);
        }

        return $this->getJSONSetting('youtrack', $name);
    }

    protected function getJSONSetting($node, $name)
    {
        if (! $this->json_settings) {
            $this->readJSONSettings();
        }
        if ($this->json_settings && array_key_exists($node, $this->json_settings)
            && array_key_exists(
                $name,
                $this->json_settings[$node]
            )
        ) {
            return $this->json_settings[$node][$name];
        }

        return null;
    }

    protected function readJSONSettings()
    {
        $json_settings_uri = '../../build/settings.json';
        if (file_exists($json_settings_uri)) {
            $this->json_settings = json_decode($this->stream->get($json_settings_uri), true);

            return;
        }
        $json_settings_uri = './build/settings.json';
        if (file_exists($json_settings_uri)) {
            $this->json_settings = json_decode($this->stream->get($json_settings_uri), true);

            return;
        }
    }

    protected function extractSemVer($version)
    {

        $replaced1 = str_replace(array('-', '_', '+'), '.', trim($version));
        $replaced2 = preg_replace('/([^0-9\.]+)/', '.$1.', $replaced1);
        $replaced3 = str_replace('..', '.', $replaced2);
        $elements  = explode('.', $replaced3);

        $naked_elements = array('0', '0', '0');

        $elements_count = 0;
        foreach ($elements as $element) {
            if ($elements_count === 3 || ! is_numeric($element)) {
                break;
            }
            $naked_elements[$elements_count] = $element;
            $elements_count++;
        }

        return implode('.', $naked_elements);
    }
}
