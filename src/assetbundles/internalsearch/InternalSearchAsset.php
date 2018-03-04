<?php
/**
 * Internal Search plugin for Craft CMS 3.x
 *
 * Fast internal search
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2017 Superbig
 */

namespace superbig\internalsearch\assetbundles\InternalSearch;

use Craft;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * @author    Superbig
 * @package   InternalSearch
 * @since     1.0.0
 */
class InternalSearchAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->sourcePath = "@superbig/internalsearch/assetbundles/internalsearch/dist";

        $this->depends = [
            CpAsset::class,
        ];

        $this->js = [
            'js/InternalSearch.js',
        ];

        $this->css = [
            'css/InternalSearch.css',
        ];

        parent::init();
    }
}
