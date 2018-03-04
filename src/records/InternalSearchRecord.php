<?php
/**
 * Internal Search plugin for Craft CMS 3.x
 *
 * Fast internal search
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2017 Superbig
 */

namespace superbig\internalsearch\records;

use superbig\internalsearch\InternalSearch;

use Craft;
use craft\db\ActiveRecord;

/**
 * @author    Superbig
 * @package   InternalSearch
 * @since     1.0.0
 */
class InternalSearchRecord extends ActiveRecord
{
    // Public Static Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%internalsearch_internalsearchrecord}}';
    }
}
