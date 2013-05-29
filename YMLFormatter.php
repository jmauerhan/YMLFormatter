<?php

use Behat\Behat\Formatter\PrettyFormatter;
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

    protected $outputDir;
    protected $featuresDir = 'C:\wamp\www\cems\features\\';
    protected $tagsFile;
    protected $featuresFile;
    protected $failuresFile;
    protected $featureFile;
    protected $tags = array();
    protected $features = array();
    protected $failures = array();
    protected $feature = array();
    protected $scenario = array();

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

    /**
     * Listens to "suite.before" event.
     *
     * @param SuiteEvent $event
     *
     * @uses printSuiteHeader()
     */
    public function beforeSuite(SuiteEvent $event)
    {
        $this->outputDir = $this->parameters->get('output_path');
        $this->resultTypes = array(
            StepEvent::PASSED => 'success',
            StepEvent::SKIPPED => 'info',
            StepEvent::PENDING => 'warning',
            StepEvent::UNDEFINED => 'warning',
            StepEvent::FAILED => 'error'
        );

        $this->tagsFile = $this->outputDir . DIRECTORY_SEPARATOR . 'tags.yml';
        $this->featuresFile = $this->outputDir . DIRECTORY_SEPARATOR . 'features.yml';

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
        file_put_contents($this->outputDir . DIRECTORY_SEPARATOR . $this->filename . '.yml', $yamlFeatureArray);
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
            $arr['arguments'] = array();
            foreach ($step->getArguments() AS $argument)
            {
                $arr['arguments'][] = $argument->getHash();
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
        $this->scenario['result'] = $this->resultTypes[$event->getResult()];
        $this->feature['scenarios'][] = $this->scenario;
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
                $this->addScenarioTag($tag, $event->getOutline()->getTitle(), $event->getOutline()->getLine());
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
        $this->scenario['result'] = $this->resultTypes[$event->getResult()];
        $this->feature['scenarios'][] = $this->scenario;
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

}

