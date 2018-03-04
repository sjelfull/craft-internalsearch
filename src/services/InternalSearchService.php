<?php
/**
 * Internal Search plugin for Craft CMS 3.x
 *
 * Fast internal search
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2017 Superbig
 */

namespace superbig\internalsearch\services;

use craft\base\Element;
use craft\base\ElementInterface;
use craft\config\DbConfig;
use craft\helpers\Db;
use craft\helpers\FileHelper;
use craft\helpers\StringHelper;
use craft\helpers\Search as SearchHelper;
use craft\models\Site;
use Illuminate\Support\Collection;
use superbig\internalsearch\InternalSearch;

use Craft;
use craft\base\Component;
use TeamTNT\TNTSearch\Indexer\TNTIndexer;
use TeamTNT\TNTSearch\TNTSearch;
use yii\db\Schema;

/**
 * @author    Superbig
 * @package   InternalSearch
 * @since     1.0.0
 */
class InternalSearchService extends Component
{
    // Public Methods
    // =========================================================================

    /**
     * @var TNTSearch
     */
    protected $searchClient;
    private   $indexName;
    private   $index;

    public function init()
    {
        parent::init();

        $this->searchClient = new TNTSearch;

        $storagePath = Craft::$app->path->getStoragePath() . '/internalsearch/';

        // Ensure its there
        FileHelper::createDirectory($storagePath);

        $this->searchClient->loadConfig([
            'driver'   => 'sqlite',
            'database' => $storagePath . 'search.sqlite',
            'storage'  => $storagePath,
        ]);
    }

    public function createIndexes()
    {
        $sites             = new Collection(Craft::$app->getSites()->getAllSites());
        $indexSitesHandles = InternalSearch::$plugin->getSettings()->siteHandles;
        $elementTypes      = InternalSearch::$plugin->getSettings()->elementTypes;

        var_dump($elementTypes);
        // Entries
        $sections = Craft::$app->getSections()->getAllSections();
        //$elementTypes = Craft::$app->getElements()->getAllElementTypes();

        $indexes = $sites
            ->filter(function(
                /** @var Site */
                $site) {
                return empty($indexSitesHandles) || in_array($site->handle, $indexSitesHandles);
            })
            ->map(function(
                /** @var Site */
                $site) use ($elementTypes) {

                $siteIndexes = [];

                // Loop through element types
                foreach ($elementTypes as $key => $handle) {
                    if (is_array($handle)) {
                        $handle   = $key;
                        $criteria = $handle;
                    }

                    $index = $this->getIndexHandle($handle, $site->handle);

                    if ($this->searchClient->createIndex($index)) {
                        $siteIndexes[ $handle ] = $index;
                    }
                }

                return $siteIndexes;
            });

        /*
         * if ($this->searchClient->createIndex($index)) {
                    $indexes[] = $index;
                }
         */

        return $indexes;

        // Assets
        // Users
    }

    public function listIndexes()
    {
        return $this->searchClient->getIndex();
    }

    public function search($search = '', $index = null)
    {
        if ($index) {
            $this->setIndexName($index);
        }

        $index                         = $this->getIndex();
        $this->searchClient->fuzziness = true;

        $result = $this->searchClient->search($search);
        $ids    = $result['ids'];

        return $result;
        //SELECT * FROM articles WHERE id IN $res ORDER BY FIELD(id, $res);
        //return $index->query("SELECT * FROM articles WHERE id IN  ORDER BY FIELD(id, )")
    }

    public function indexDocument($data = [], $index = null)
    {
        if ($index && $index !== $this->indexName) {
            $this->setIndexName($index);
        }

        $index = $this->getIndex();

        $index->insert($data);

        return true;
    }

    public function getIndexHandle($elementType, $siteHandle)
    {
        return strtolower(str_replace('\\', '-', $elementType) . '-' . $siteHandle);
    }

    /**
     * @param ElementInterface $element
     *
     * @return bool
     */
    public function onSaveElement(ElementInterface $element): bool
    {
        /** @var Element $element */
        // Does it have any searchable attributes?
        $searchableAttributes = $element::searchableAttributes();

        $searchableAttributes[] = 'slug';

        if ($element::hasTitles()) {
            $searchableAttributes[] = 'title';
        }

        foreach ($searchableAttributes as $attribute) {
            $value = $element->getSearchKeywords($attribute);
            $this->_indexElementKeywords($element->id, $attribute, '0', $element->siteId, $value);
        }

        return true;

        /*if ( $class::hasContent() && ($fieldLayout = $element->getFieldLayout()) !== null ) {
            $keywords = [];

            foreach ($fieldLayout->getFields() as $field) {*/
        // /** @var Field $field */
        /*
                // Set the keywords for the content's site
                $fieldValue             = $element->getFieldValue($field->handle);
                $fieldSearchKeywords    = $field->getSearchKeywords($fieldValue, $element);
                $keywords[ $field->id ] = $fieldSearchKeywords;
            }

            Craft::$app->getSearch()->indexElementFields($element->id, $siteId, $keywords);
        }*/
    }

    /**
     * Indexes keywords for a specific element attribute/field.
     *
     * @param int      $elementId
     * @param string   $attribute
     * @param string   $fieldId
     * @param int|null $siteId
     * @param string   $dirtyKeywords
     *
     * @return void
     * @throws \craft\errors\SiteNotFoundException
     */
    private function _indexElementKeywords(int $elementId, string $attribute, string $fieldId, int $siteId = null, string $dirtyKeywords)
    {
        $attribute = StringHelper::toLowerCase($attribute);
        $driver    = Craft::$app->getDb()->getDriverName();

        if ($siteId === null) {
            $siteId = Craft::$app->getSites()->getPrimarySite()->id;
        }

        // Clean 'em up
        $cleanKeywords = SearchHelper::normalizeKeywords($dirtyKeywords);

        // Save 'em
        $keyColumns = [
            'elementId' => $elementId,
            'attribute' => $attribute,
            'fieldId'   => $fieldId,
            'siteId'    => $siteId,
        ];

        if ($cleanKeywords !== null && $cleanKeywords !== false && $cleanKeywords !== '') {
            // Add padding around keywords
            $cleanKeywords = ' ' . $cleanKeywords . ' ';
        }

        if ($driver === DbConfig::DRIVER_PGSQL) {
            $maxSize = $this->maxPostgresKeywordLength;
        }
        else {
            $maxSize = Db::getTextualColumnStorageCapacity(Schema::TYPE_TEXT);
        }

        if ($maxSize !== null && $maxSize !== false) {
            $cleanKeywords = $this->_truncateSearchIndexKeywords($cleanKeywords, $maxSize);
        }

        $keywordColumns = ['keywords' => $cleanKeywords];

        if ($driver === DbConfig::DRIVER_PGSQL) {
            $keywordColumns['keywords_vector'] = $cleanKeywords;
        }

        // Insert/update the row in searchindex
        Craft::$app->getDb()->createCommand()
                   ->upsert(
                       '{{%searchindex}}',
                       $keyColumns,
                       $keywordColumns,
                       false)
                   ->execute();
    }

    /**
     * @param string $cleanKeywords The string of space separated search keywords.
     * @param int    $maxSize       The maximum size the keywords string should be.
     *
     * @return string The (possibly) truncated keyword string.
     */
    private function _truncateSearchIndexKeywords(string $cleanKeywords, int $maxSize): string
    {
        $cleanKeywordsLength = mb_strlen($cleanKeywords);

        // Give ourselves a little wiggle room.
        /** @noinspection CallableParameterUseCaseInTypeContextInspection */
        $maxSize = ceil($maxSize * 0.95);

        if ($cleanKeywordsLength > $maxSize) {
            // Time to truncate.
            $cleanKeywords = mb_strcut($cleanKeywords, 0, $maxSize);

            // Make sure we don't cut off a word in the middle.
            if ($cleanKeywords[ mb_strlen($cleanKeywords) - 1 ] !== ' ') {
                $position = mb_strrpos($cleanKeywords, ' ');

                if ($position) {
                    $cleanKeywords = mb_substr($cleanKeywords, 0, $position + 1);
                }
            }
        }

        return $cleanKeywords;
    }

    /**
     * @param ElementInterface $element
     *
     * @return array
     */
    public function getContent(ElementInterface $element)
    {
        /** @var Element $element */
        $dirtyKeywords          = [];
        $content                = [];
        $searchableAttributes   = $element::searchableAttributes();
        $searchableAttributes[] = 'slug';

        if ($element::hasTitles()) {
            $searchableAttributes[] = 'title';
        }

        foreach ($searchableAttributes as $attribute) {
            $value         = $element->getSearchKeywords($attribute);
            $cleanKeywords = SearchHelper::normalizeKeywords($value);

            $content[ $attribute ] = $cleanKeywords;
        }

        $content['id'] = $element->id;

        return $content;

        /** @var Element $element */
        /* if ($element->hasContent()) {
            $basic = $element->getSerializedFieldValues();
            $basic = $element->getSearchKeywords();
        }*/
    }

    /**
     * @return TNTIndexer
     */
    public function getIndex()
    {
        return $this->index;
    }

    public function setIndexName($index = null)
    {
        $this->indexName = $index;
        $this->searchClient->selectIndex($this->indexName);
        $this->index = $this->searchClient->getIndex();

        return $this;
    }
}
