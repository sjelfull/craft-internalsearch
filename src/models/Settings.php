<?php
/**
 * Internal Search plugin for Craft CMS 3.x
 *
 * Fast internal search
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2017 Superbig
 */

namespace superbig\internalsearch\models;

use superbig\internalsearch\InternalSearch;

use Craft;
use craft\base\Model;

/**
 * @author    Superbig
 * @package   InternalSearch
 * @since     1.0.0
 */
class Settings extends Model
{
    // Public Properties
    // =========================================================================

    /**
     * Element types and their criteria
     *
     * @var array
     */
    public $elementTypes = [];

    /**
     * Site handles to index - all by default
     *
     * @var array
     */
    public $siteHandles = [];

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            //['someAttribute', 'string'],
            //['someAttribute', 'default', 'value' => 'Some Default'],
        ];
    }
}
