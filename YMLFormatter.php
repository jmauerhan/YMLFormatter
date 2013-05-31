<?php

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag,
    Behat\Behat\Formatter\PrettyFormatter;
use Behat\Behat\Definition\DefinitionInterface,
    Behat\Behat\Definition\DefinitionSnippet,
    Behat\Behat\DataCollector\LoggerDataCollector,
    Behat\Behat\Event\SuiteEvent,
    Behat\Behat\Event\FeatureEvent,
    Behat\Behat\Event\ScenarioEvent,
    Behat\Behat\Event\BackgroundEvent,
    Behat\Behat\Event\OutlineEvent,
    Behat\Behat\Event\OutlineExampleEvent,
    Behat\Behat\Event\StepEvent,
    Behat\Behat\Event\EventInterface,
    Behat\Behat\Exception\UndefinedException;
use Behat\Gherkin\Node\AbstractNode,
    Behat\Gherkin\Node\FeatureNode,
    Behat\Gherkin\Node\BackgroundNode,
    Behat\Gherkin\Node\AbstractScenarioNode,
    Behat\Gherkin\Node\OutlineNode,
    Behat\Gherkin\Node\ScenarioNode,
    Behat\Gherkin\Node\StepNode,
    Behat\Gherkin\Node\ExampleStepNode,
    Behat\Gherkin\Node\PyStringNode,
    Behat\Gherkin\Node\TableNode;
use Symfony\Component\Yaml\Parser,
    Symfony\Component\Yaml\Dumper;

class YMLFormatter extends PrettyFormatter
{

    /**
     * The folder in which the config yml files will be placed (tags.yml, features.yml, failed.yml, etc)
     *
     * @var string
     */
    protected $docsDir;

    /**
     * The folder in which the individual feature yml files will be placed.
     *
     * @var string
     */
    protected $ymlFeaturesDir;

    /**
     * The name of the tags config file.
     *
     * @var string
     */
    protected $tagsFile = 'tags.yml';

    /**
     * The name of the features config file.
     *
     * @var string
     */
    protected $featuresFile = 'features.yml';

    /**
     * The name of the failures config file.
     *
     * @var string
     */
    protected $failuresFile = 'failures.yml';

    /**
     * The folder in which the .feature files are located
     *
     * @var string
     */
    protected $featuresDir = 'C:\wamp\www\cems\features\\';

    /**
     * All of the existing tags
     *
     * @var array
     */
    protected $tags = array();

    /**
     * All of the existing features
     *
     * @var array
     */
    protected $features = array();

    /**
     * All of the existing failures
     *
     * @var array
     */
    protected $failures = array();

    /**
     * The current feature (not yet written to a file)
     *
     * @var array
     */
    protected $feature = array();

    /**
     * The current scenarion (not yet stored in $this->feature)
     *
     * @var array
     */
    protected $scenario = array();

    /**
     * Initialize formatter.
     *
     * @uses getDefaultParameters()
     */
    public function __construct()
    {
        $this->parameters = new ParameterBag(array_merge(array(
                    'hard_reset' => false
                        ), $this->getDefaultParameters()));
    }

    public static function getSubscribedEvents()
    {
        $events = array(
            'beforeSuite', 'afterSuite', 'beforeFeature', 'afterFeature', 'beforeScenario',
            'afterScenario', 'beforeBackground', 'afterBackground', 'beforeOutline', 'afterOutline',
            'beforeOutlineExample', 'afterOutlineExample', 'beforeStep', 'afterStep'
        );

        return array_combine($events, $events);
    }

    public function addTag($tag, $featureTitle)
    {
        if (!isset($this->tags[$tag]))
        {
            $this->tags[$tag] = array();
        }
        if (!isset($this->tags[$tag][$this->filename]))
        {
            $this->tags[$tag][$this->filename] = array();
        }
        $this->tags[$tag][$this->filename]['feature'] = $featureTitle;
    }

    public function addScenarioTag($tag, $scenarioTitle, $scenarioLine, $featureTitle)
    {
        if (!isset($this->tags[$tag]))
        {
            $this->tags[$tag] = array();
        }
        if (!isset($this->tags[$tag][$this->filename]))
        {
            $this->tags[$tag][$this->filename] = array();
        }
        $this->tags[$tag][$this->filename]['scenarios'][$scenarioLine] = array(
            'feature' => $featureTitle,
            'scenario' => $scenarioTitle
        );
    }

    public function processParameters()
    {
        $this->docsDir = $this->parameters->get('output_path');
        $this->ymlFeaturesDir = $this->docsDir . DIRECTORY_SEPARATOR . 'features';
        if (!is_dir($this->docsDir))
        {
            mkdir($this->docsDir);
        }
        if (!is_dir($this->ymlFeaturesDir))
        {
            mkdir($this->ymlFeaturesDir);
        }

        if ($this->hasParameter('tags_file'))
        {
            $this->tagsFile = $this->docsDir . DIRECTORY_SEPARATOR . $this->parameters->get('tags_file');
        }
        else
        {
            $this->tagsFile = $this->docsDir . DIRECTORY_SEPARATOR . 'tags.yml';
        }

        if ($this->hasParameter('features_file'))
        {
            $this->featuresFile = $this->docsDir . DIRECTORY_SEPARATOR . $this->parameters->get('features_file');
        }
        else
        {
            $this->featuresFile = $this->docsDir . DIRECTORY_SEPARATOR . 'features.yml';
        }

        if ($this->hasParameter('failures_file'))
        {
            $this->failuresFile = $this->docsDir . DIRECTORY_SEPARATOR . $this->parameters->get('failures_file');
        }
        else
        {
            $this->failuresFile = $this->docsDir . DIRECTORY_SEPARATOR . 'failures.yml';
        }

        if ($this->hasParameter('hard_reset') && $this->parameters->get('hard_reset'))
        {
            $this->hardReset();
        }
    }

    /**
     * Listens to "suite.before" event.
     *
     * @param SuiteEvent $event
     *
     * @uses printSuiteHeader()
     */
    public function beforeSuite(SuiteEvent $event)
    {
        $this->processParameters();

        $this->resultTypes = array(
            StepEvent::PASSED => 'success',
            StepEvent::SKIPPED => 'info',
            StepEvent::PENDING => 'warning',
            StepEvent::UNDEFINED => 'warning',
            StepEvent::FAILED => 'error'
        );

        //Read in existing data
        if (is_file($this->tagsFile))
        {
            $yaml = new Parser();
            $this->tags = $yaml->parse(file_get_contents($this->tagsFile));
        }

        if (is_file($this->featuresFile))
        {
            $yaml = new Parser();
            $this->features = $yaml->parse(file_get_contents($this->featuresFile));
        }
    }

    /**
     * Listens to "suite.after" event.
     *
     * @param SuiteEvent $event
     *
     * @uses printSuiteFooter()
     */
    public function afterSuite(SuiteEvent $event)
    {
        $this->flushOutputConsole();

        $dumper = new Dumper();
        asort($this->features);
        $yamlFeatures = $dumper->dump($this->features, 6);
        file_put_contents($this->featuresFile, $yamlFeatures);

        ksort($this->tags);
        $yamlTags = $dumper->dump($this->tags, 4);
        file_put_contents($this->tagsFile, $yamlTags);

        ksort($this->failures);
        $yamlFailures = $dumper->dump($this->failures, 4);
        file_put_contents($this->failuresFile, $yamlFailures);
    }

    /**
     * Listens to "feature.before" event.
     *
     * @param FeatureEvent $event
     *
     * @uses printFeatureHeader()
     */
    public function beforeFeature(FeatureEvent $event)
    {
        $this->flushOutputConsole();
        $feature = $event->getFeature();

        //Remove the leading directory and the .feature, then convert slashes to hyphens to create a single string for the filename.
        $fileDir = $feature->getFile();
        $fileDirShort = str_replace($this->featuresDir, "", $fileDir);
        $fileDirTrimmed = str_replace(".feature", "", $fileDirShort);

        $directories = explode("\\", $fileDirTrimmed);
        array_pop($directories); // Remove the file to get just the directory structure. (used later to

        $this->filename = str_replace("\\", '-', $fileDirTrimmed);

        $tags = $feature->getTags();
        sort($tags);
        foreach ($tags AS $tag)
        {
            $this->addTag($tag, $feature->getTitle());
        }
        $title = $feature->getTitle();

        $this->feature = array(
            'tags' => $tags,
            'title' => $title,
            'description' => $feature->getDescription()
        );

        $arr = array(
            'title' => addslashes($title),
            'tags' => $tags,
            'location' => $directories
        );
        $dir = '$this->features[\'' . implode("']['", $directories) . "']['" . $this->filename . "'] = " . '$arr' . ";";
        eval($dir);
    }

    /**
     * Listens to "feature.after" event.
     *
     * @param FeatureEvent $event
     *
     * @uses printFeatureFooter()
     */
    public function afterFeature(FeatureEvent $event)
    {
        $this->feature['result'] = $this->resultTypes[$event->getResult()];
        $dumper = new Dumper();
        $yamlFeatureArray = $dumper->dump($this->feature, 10);
        file_put_contents($this->ymlFeaturesDir . DIRECTORY_SEPARATOR . $this->filename . '.yml', $yamlFeatureArray);
        unset($this->feature);
    }

    /**
     * Listens to "background.before" event.
     *
     * @param BackgroundEvent $event
     *
     * @uses printBackgroundHeader()
     */
    public function beforeBackground(BackgroundEvent $event)
    {
        $this->inBackground = true;
        $this->feature['background'] = array();
    }

    /**
     * Listens to "background.after" event.
     *
     * @param BackgroundEvent $event
     *
     * @uses printBackgroundFooter()
     */
    public function afterBackground(BackgroundEvent $event)
    {
        $this->inBackground = false;
        $this->feature['background']['result'] = $this->resultTypes[$event->getResult()];
    }

    /**
     * Listens to "outline.before" event.
     *
     * @param OutlineEvent $event
     *
     * @uses printOutlineHeader()
     */
    public function beforeOutline(OutlineEvent $event)
    {
        $this->scenario = array(
            'title' => $event->getOutline()->getTitle()
        );
        $tags = $event->getOutline()->getOwnTags();
        if (count($tags))
        {
            $this->scenario['tags'] = $tags;
            foreach ($tags AS $tag)
            {
                $this->addScenarioTag($tag, $event->getOutline()->getTitle(), $event->getOutline()->getLine(),
                        $event->getOutline()->getFeature()->getTitle());
            }
        }
        $this->scenario['keyword'] = $event->getOutline()->getKeyword();
        $examples = $event->getOutline()->getExamples();
        $this->scenario['examples'] = $examples->getHash();
    }

    /**
     * Listens to "outline.example.before" event.
     *
     * @param OutlineExampleEvent $event
     *
     * @uses printOutlineExampleHeader()
     */
    public function beforeOutlineExample(OutlineExampleEvent $event)
    {
        $this->inOutlineExample = true;
        foreach ($event->getOutline()->getSteps() AS $step)
        {
            $this->scenario['steps'][$step->getLine()] = $this->flattenStep($step);
        }
    }

    public function flattenStep(StepNode $step)
    {
        $arr = array(
            'type' => $step->getType(),
            'text' => $step->getText()
        );
        if ($step->hasArguments())
        {
            $arr['argument'] = array();
            foreach ($step->getArguments() AS $argument)
            {
                if ($argument instanceof TableNode)
                {
                    $arr['argument']['type'] = 'table';
                    $arr['argument']['data'] = $argument->getHash();
                }
                else if ($argument instanceOf PyStringNode)
                {
                    $arr['argument']['type'] = 'string';
                    $arr['argument']['data'] = $argument->getLines();
                }
            }
        }
        return $arr;
    }

    /**
     * Listens to "outline.example.after" event.
     *
     * @param OutlineExampleEvent $event
     *
     * @uses printOutlineExampleFooter()
     */
    public function afterOutlineExample(OutlineExampleEvent $event)
    {
        $this->inOutlineExample = false;
        $this->scenario['examples'][$event->getIteration()] = array(
            'data' => $this->scenario['examples'][$event->getIteration()],
            'result' => $this->resultTypes[$event->getResult()]
        );
    }

    /**
     * Listens to "outline.after" event.
     *
     * @param OutlineEvent $event
     *
     * @uses printOutlineFooter()
     */
    public function afterOutline(OutlineEvent $event)
    {
        $this->logScenario($event, $event->getOutline());
    }

    public function logScenario(Symfony\Component\EventDispatcher\Event $event, $scenario)
    {
        $this->scenario['result'] = $this->resultTypes[$event->getResult()];
        $this->feature['scenarios'][$scenario->getLine()] = $this->scenario;
        if ($this->scenario['result'] != $this->resultTypes[0])
        {
            $this->failures[$this->filename]['feature'] = $scenario->getFeature()->getTitle();
            $this->failures[$this->filename]['scenarios'][$scenario->getLine()]['scenario'] = $scenario->getTitle();
            //$this->failures[$this->filename]['scenarios'][$scenario->getLine()]['step'] =
        }
        unset($this->scenario);
    }

    /**
     * Listens to "scenario.before" event.
     *
     * @param ScenarioEvent $event
     *
     * @uses printScenarioHeader()
     */
    public function beforeScenario(ScenarioEvent $event)
    {
        $this->scenario = array(
            'title' => $event->getScenario()->getTitle()
        );
        $tags = $event->getScenario()->getOwnTags();
        if (count($tags))
        {
            $this->scenario['tags'] = $tags;
            foreach ($tags AS $tag)
            {
                $this->addScenarioTag($tag, $event->getScenario()->getTitle(), $event->getScenario()->getLine(),
                        $event->getScenario()->getFeature()->getTitle());
            }
        }
        $this->scenario['keyword'] = $event->getScenario()->getKeyword();
    }

    /**
     * Listens to "scenario.after" event.
     *
     * @param ScenarioEvent $event
     *
     * @uses printScenarioFooter()
     */
    public function afterScenario(ScenarioEvent $event)
    {
        $this->logScenario($event, $event->getScenario());
    }

    /**
     * Listens to "step.before" event.
     *
     * @param StepEvent $event
     *
     * @uses printStep()
     */
    public function beforeStep(StepEvent $event)
    {

    }

    /**
     * Listens to "step.after" event.
     *
     * @param StepEvent $event
     *
     * @uses printStep()
     */
    public function afterStep(StepEvent $event)
    {
        $step = $event->getStep();
        $stepArr = $this->flattenStep($step);
        $stepArr['result'] = $this->resultTypes[$event->getResult()];

        if ($this->inBackground)
        {
            $this->feature['background']['steps'][$step->getLine()] = $stepArr;
        }
        else if ($this->inOutlineExample)
        {
            $this->scenario['executedSteps'][] = $stepArr;
        }
        else
        {
            $this->scenario['steps'][$step->getLine()] = $stepArr;
        }
    }

    public function hardReset()
    {
        $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->outputDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo)
        {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getRealPath());
        }
    }

}

