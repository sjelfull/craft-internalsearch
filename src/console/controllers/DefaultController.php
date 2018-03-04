<?php
/**
 * Internal Search plugin for Craft CMS 3.x
 *
 * Fast internal search
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2017 Superbig
 */

namespace superbig\internalsearch\console\controllers;

use craft\base\Element;
use superbig\internalsearch\InternalSearch;

use Craft;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Default Command
 *
 * @author    Superbig
 * @package   InternalSearch
 * @since     1.0.0
 */
class DefaultController extends Controller
{
    public $index;
    public $query;

    // Public Methods
    // =========================================================================

    public function options($actionId)
    {
        return ['index', 'query'];
    }

    /**
     * Index sections
     *
     * @return mixed
     */
    public function actionIndex()
    {
        $types = [
            'Entries' => Entry::class,
            'Tags'    => Tag::class,
            'Users'   => User::class,
        ];

        $elements = [];

        foreach ($types as $title => $class) {
            $elements = array_merge($elements, $class::find()->all());
        }

        Console::startProgress(0, count($elements));

        $this->stdout(sprintf("Indexing %d entries, globalsets and drafts\n", count($elements)), Console::BOLD);

        $done = 0;

        foreach ($elements as $element) {
            $done++;

            Console::updateProgress($done, count($elements));
        }


        Console::endProgress();
    }

    /**
     * Create indexes
     *
     * @return mixed
     */
    public function actionCreateIndexes()
    {
        $indexes = InternalSearch::$plugin->internalSearchService->createIndexes();

        foreach ($indexes as $site => $siteIndexes) {

            foreach ($siteIndexes as $elementClass => $indexHandle) {
                InternalSearch::$plugin->internalSearchService->setIndexName($indexHandle);

                $this->writeLn('Indexing: ' . $indexHandle);

                /** @var Element $element */
                $element = new $elementClass();
                $hits    = $element::find()->limit(null)->all();

                foreach ($hits as $hit) {
                    $content = InternalSearch::$plugin->internalSearchService->getContent($hit);

                    InternalSearch::$plugin->internalSearchService->indexDocument($content);
                }

                $this->writeLn('Indexed: ' . $indexHandle);
                //$this->prompt('Test thing prompt?');
            }
        }

        return ExitCode::OK;
    }

    public function actionSearch()
    {
        if (empty($this->index)) {
            $this->writeLn('Invalid index');

            return ExitCode::DATAERR;
        }

        if (empty($this->query)) {
            $this->writeLn('Invalid query');

            return ExitCode::DATAERR;
        }

        InternalSearch::$plugin->internalSearchService->setIndexName($this->index);

        $result = InternalSearch::$plugin->internalSearchService->search($this->query);

        $this->writeLn("Found " . $result['hits'] . " hits");

        print_r($result);
    }

    /**
     * Outputs a string to the console.
     *
     * @param $str
     */
    private function write($str)
    {
        $this->stdout($str);
    }

    /**
     * Outputs a line to the console.
     *
     * @param string $str
     */
    private function writeLn($str = '')
    {
        $str = (is_array($str) ? implode(PHP_EOL, $str) : $str) . PHP_EOL;

        $this->stdout($str);
    }
}
