<?php
/**
 * Internal Search plugin for Craft CMS 3.x
 *
 * Fast internal search
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2017 Superbig
 */

namespace superbig\internalsearch;

use craft\events\ElementEvent;
use craft\services\Elements;
use superbig\internalsearch\services\InternalSearchService as InternalSearchServiceService;
use superbig\internalsearch\models\Settings;

use Craft;
use craft\base\Plugin;
use craft\services\Plugins;
use craft\events\PluginEvent;
use craft\console\Application as ConsoleApplication;
use craft\web\UrlManager;
use craft\events\RegisterUrlRulesEvent;

use yii\base\Event;

/**
 * Class InternalSearch
 *
 * @author    Superbig
 * @package   InternalSearch
 * @since     1.0.0
 *
 * @property  InternalSearchServiceService $internalSearchService
 * @method  Settings getSettings()
 */
class InternalSearch extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * @var InternalSearch
     */
    public static $plugin;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        self::$plugin = $this;

        if (Craft::$app instanceof ConsoleApplication) {
            $this->controllerNamespace = 'superbig\internalsearch\console\controllers';
        }

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['siteActionTrigger1'] = 'internal-search/default';
            }
        );

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['cpActionTrigger1'] = 'internal-search/default/do-something';
            }
        );

        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_INSTALL_PLUGIN,
            function(PluginEvent $event) {
                if ($event->plugin === $this) {
                }
            }
        );

        Event::on(
            Elements::class,
            Elements::EVENT_AFTER_SAVE_ELEMENT,
            function(ElementEvent $event) {
                $this->internalSearchService->onSaveElement($event->element);
            }
        );

        Craft::info(
            Craft::t(
                'internal-search',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function createSettingsModel()
    {
        return new Settings();
    }

    /**
     * @inheritdoc
     */
    protected function settingsHtml(): string
    {
        return Craft::$app->view->renderTemplate(
            'internal-search/settings',
            [
                'settings' => $this->getSettings(),
            ]
        );
    }
}
