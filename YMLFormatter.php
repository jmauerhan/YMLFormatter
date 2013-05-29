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

    /**
     * List of features with all of their relevant attributes (tags, scenarios, outlines, examples)
     *
     * @var array
     */
    protected $features;

    /**
     * Current feature array, not yet written to a file.
     *
     * @var array
     */
    protected $feature;

    /**
     * Currently in a scenario
     *
     * @var type boolean
     */
    protected $inScenario;

    /*     * f
     * Current scenario array, not yet added to the features array.
     *
     * @var array
     */
    protected $scenario = false;
    protected $inScenarioOutline = false;

    /**
     * List of tags that were run
     *
     * @var array
     */
    protected $tags = array();

    /**
     * Current HTML filename.
     *
     * @var string
     */
    protected $filename;

    /**
     * Documentation Directory
     *
     * @var string
     */
    protected $outputPath;
    protected $tagsFile;
    protected $featuresFile;
    protected $featuresDir = 'C:\\wamp\\www\\cems\\features\\';

    /**
     * Result types
     *
     * @var array
     */
    protected $resultTypes;

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (priority defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     *
     * For instance:
     *
     *  * array('eventName' => 'methodName')
     *  * array('eventName' => array('methodName', $priority))
     *  * array('eventName' => array(array('methodName1', $priority), array('methodName2'))
     *
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents()
    {
        $events = array(
            'beforeSuite', 'afterSuite', 'beforeFeature', 'afterFeature', 'afterStep', 'beforeBackground', 'afterBackground', 'beforeScenario', 'afterScenario', 'beforeOutline', 'afterOutline', 'beforeOutlineExample', 'afterOutlineExample'
        );

        return array_combine($events, $events);
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
        $this->outputPath = $this->parameters->get('output_path');
        $this->resultTypes = array(
            StepEvent::PASSED => 'success',
            StepEvent::SKIPPED => 'info',
            StepEvent::PENDING => 'warning',
            StepEvent::UNDEFINED => 'warning',
            StepEvent::FAILED => 'error'
        );

        $this->tagsFile = $this->outputPath . DIRECTORY_SEPARATOR . 'tags.yml';
        $this->featuresFile = $this->outputPath . DIRECTORY_SEPARATOR . 'features.yml';
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
        $this->filename = 'index . html
                 ';

        $dumper = new Dumper();
        asort($this->features);
        $yamlFeatures = $dumper->dump($this->features, 6);
        file_put_contents($this->outputPath . DIRECTORY_SEPARATOR . "features.yml", $yamlFeatures);

        //$this->tags = array_unique($this->tags);
        asort($this->tags);
        $yamlTags = $dumper->dump($this->tags, 2);
        file_put_contents($this->outputPath . DIRECTORY_SEPARATOR . "tags.yml", $yamlTags);
    }

    /**
     * Listens to "feature.before" event.
     *
     * @param FeatureEvent $event
     *
     * @uses printTestSuiteHeader()
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
        $title = $feature->getTitle();

        $this->feature = array(
            'tags' => $tags,
            'title' => $title,
            'description' => $feature->getDescription()
        );
        /* if ($background)
          {
          $bg = array();
          if ($background->getTitle())
          {
          $bg['title'] = $background->getTitle();
          }
          $bg['steps'] = $this->yamlFlattenSteps($background->getSteps());

          $this->feature['background'] = $bg;
          }
          /* foreach ($scenarios AS $scenario)
          {
          $sArray = array();
          $sArray['keyword'] = $scenario->getKeyword();
          if ($scenario->getOwnTags())
          {
          $sArray['tags'] = $scenario->getOwnTags();
          }
          $sArray['title'] = $scenario->getTitle();
          $sArray['steps'] = $this->yamlFlattenSteps($scenario->getSteps());
          if ($scenario->getKeyword() == 'Scenario Outline')
          {
          if ($scenario->hasExamples())
          {
          $sArray['examples'] = $scenario->getExamples()->getHash();
          }
          }
          $featureArray['scenarios'][$scenario->getLine()] = $sArray;
          } */
        //$featureArray['directory'] = $directories;
        //Create the feature file

        $arr = array(
            'title' => addslashes($title),
            'tags' => $tags,
            'location' => $directories
        );
        $dir = '$this->features[\'' . implode("']['", $directories) . "']['" . $this->filename . "'] = " . '$arr' . ";";
        eval($dir);

        foreach ($tags AS $tag)
        {
            $this->tags[$tag][$this->filename] = $feature->getTitle();
        }
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
        $dumper = new Dumper();
        $yamlFeatureArray = $dumper->dump($this->feature, 10);
        file_put_contents($this->outputPath . DIRECTORY_SEPARATOR . $this->filename . '.yml', $yamlFeatureArray);
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
        if ($event->getBackground())
        {
            $this->feature['background'] = array();
        }
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
        $tags = $event->getOutline()->getOwnTags();
        $this->scenario = array(
            'keyword' => $event->getOutline()->getKeyword(),
            'title' => $event->getOutline()->getTitle(),
        );
        if (count($tags))
        {
            $this->scenario['tags'] = $tags;
        }
        $this->inScenarioOutline = true;
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
        $this->inScenarioOutline = false;
        $this->scenario['result'] = $this->resultTypes[$event->getResult()];
        $this->feature['scenarios'][] = $this->scenario;
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

        //$this->scenario['examples'];
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
        /* $steps = $event->getOutline()->getSteps();
          foreach ($steps AS $step)
          {
          $stepArray = array(
          'type' => $step->getType(),
          'text' => $step->getText()
          );
          $this->scenario['steps'][] = $stepArray;
          } */
        $this->inOutlineExample = false;
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
        $tags = $event->getScenario()->getOwnTags();
        $this->scenario = array(
            'keyword' => $event->getScenario()->getKeyword(),
            'title' => $event->getScenario()->getTitle(),
        );
        if (count($tags))
        {
            $this->scenario['tags'] = $tags;
        }
        $this->inScenario = true;
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
        $this->inScenario = false;
        $this->scenario['result'] = $this->resultTypes[$event->getResult()];
        $this->feature['scenarios'][] = $this->scenario;
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
        $step = array(
            'type' => $event->getStep()->getType(),
            'text' => $event->getStep()->getText(),
            'result' => $this->resultTypes[$event->getResult()]
        );


        if ($this->inBackground)
        {
            $this->feature['background']['steps'][] = $step;
        }
        else if ($this->inScenario)
        {
            $this->scenario['steps'][] = $step;
        }
        else if ($this->inScenarioOutline)
        {
            $this->scenario['examplesExecuted'][] = $step;
            $this->delayedStepEvents[] = $event;
        }
    }

    public function yamlFlattenSteps($steps)
    {
        $arr = array();
        foreach ($steps AS $step)
        {
            $arr[$step->getLine()] = $this->yamlFlattenStep($step);
        }
        return $arr;
    }

    public
            function yamlFlattenStep($step)
    {
        $arr = array(
            'type' => $step->getType(),
            'text' => $step->getText()
        );
        if ($step->getArguments())
        {
            foreach ($step->getArguments() AS $argument)
            {
                if ($argument instanceOf TableNode)
                {
                    $arr['arguments'][] = $argument->getHash();
                }
            }
        }
        return $arr;
    }

}

