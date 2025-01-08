<?php

declare(strict_types=1);

namespace Maginium\ScopeHint\Interceptors;

use Magento\Config\Model\Config\Structure\Element\Field as Subject;
use Magento\Framework\App\State as AppState;
use Magento\Framework\Phrase;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Maginium\Framework\Support\Facades\Config;
use Maginium\Framework\Support\Facades\Request;
use Maginium\Framework\Support\Php;
use Maginium\Framework\Support\Str;

/**
 * Plugin to modify the configuration field's tooltip and comment with scope-specific hints
 * in the developer mode. It adds scope hints for websites and stores where configuration values
 * differ from the current one.
 */
class ConfigFieldPlugin
{
    /**
     * Scope type for websites.
     */
    public const SCOPE_TYPE_WEBSITES = 'websites';

    /**
     * Scope type for stores.
     */
    public const SCOPE_TYPE_STORES = 'stores';

    /**
     * @var WebsiteRepositoryInterface
     * The repository used for retrieving website data.
     */
    private $websiteRepository;

    /**
     * @var StoreRepositoryInterface
     * The repository used for retrieving store data.
     */
    private $storeRepository;

    /**
     * @var AppState
     * The application state service, used to check if the application is in developer mode.
     */
    private $appState;

    /**
     * ConfigFieldPlugin constructor.
     *
     * @param AppState $appState
     * @param StoreRepositoryInterface $storeRepository
     * @param WebsiteRepositoryInterface $websiteRepository
     */
    public function __construct(
        AppState $appState,
        StoreRepositoryInterface $storeRepository,
        WebsiteRepositoryInterface $websiteRepository,
    ) {
        // Assign injected dependencies to class properties
        $this->appState = $appState;
        $this->storeRepository = $storeRepository;
        $this->websiteRepository = $websiteRepository;
    }

    /**
     * Modify the tooltip after it is generated to include scope-specific hints.
     *
     * This method is invoked after the `getTooltip` method is called on a configuration field.
     * It adds scope hints for websites and stores only if the application is in developer mode.
     *
     * @param Subject $subject The configuration field subject.
     * @param string $result The original tooltip content.
     *
     * @return string The modified tooltip, including scope-specific hints.
     */
    public function afterGetTooltip(Subject $subject, $result)
    {
        // Initialize the tooltip lines with the original tooltip content
        $lines = [$result];

        // Add scope hints only if the application is in developer mode
        if ($this->appState->getMode() === AppState::MODE_DEVELOPER) {
            // Add website-specific scope hints
            foreach ($this->websiteRepository->getList() as $website) {
                if (! $this->isWebsiteSelected($website)) {
                    $scopeLine = $this->getScopeHint(
                        $this->getPath($subject),
                        self::SCOPE_TYPE_WEBSITES,
                        $website,
                    );

                    if ($scopeLine) {
                        $lines[] = $scopeLine;
                    }
                }
            }

            // Add store-specific scope hints
            foreach ($this->storeRepository->getList() as $store) {
                if (! $this->isStoreSelected($store)) {
                    $scopeLine = $this->getScopeHint(
                        $this->getPath($subject),
                        self::SCOPE_TYPE_STORES,
                        $store,
                    );

                    if ($scopeLine) {
                        $lines[] = $scopeLine;
                    }
                }
            }

            // Return the combined tooltip lines as HTML, separating them with a line break
            return Php::implode('<br />', array_filter($lines));
        }

        // Return the original tooltip if not in developer mode
        return $result;
    }

    /**
     * Modify the comment after it is generated to include the configuration path.
     *
     * This method is invoked after the `getComment` method is called on a configuration field.
     * It appends the configuration path to the comment in developer mode.
     *
     * @param Subject $subject The configuration field subject.
     * @param string|Phrase $result The original comment content.
     *
     * @return string|Phrase The modified comment, including the configuration path.
     */
    public function afterGetComment(Subject $subject, $result)
    {
        // Add scope hints to the comment only in developer mode
        if ($this->appState->getMode() === AppState::MODE_DEVELOPER) {
            // Convert Phrase object to string if necessary
            if ($result instanceof Phrase) {
                $result = $result->getText();
            }

            // Append a line break if the comment already contains text
            if (Str::length(Str::trim($result))) {
                $result .= '<br />';
            }

            // Append the configuration path to the comment
            $result .= __('Path: <code>%1</code>', $this->getPath($subject));
        }

        return $result;
    }

    /**
     * Retrieve the configuration path from the subject.
     *
     * This method extracts the configuration path from the given configuration field subject.
     * It checks both the `configPath` and `path` properties.
     *
     * @param Subject $subject The configuration field subject.
     *
     * @return string The configuration path.
     */
    private function getPath(Subject $subject)
    {
        // Return the config path if available, otherwise fallback to the path
        return $subject->getConfigPath() ?: $subject->getPath();
    }

    /**
     * Check if the given website is selected based on the request parameters.
     *
     * This method checks if the website is selected by comparing the website ID with the
     * `website` request parameter.
     *
     * @param WebsiteInterface $website The website model.
     *
     * @return bool True if the website is selected, false otherwise.
     */
    private function isWebsiteSelected($website)
    {
        // Compare the website ID with the request's website parameter
        return $website->getId() === Request::getParam('website');
    }

    /**
     * Check if the given store is selected based on the request parameters.
     *
     * This method checks if the store is selected by comparing the store ID with the
     * `store` request parameter.
     *
     * @param StoreInterface $store The store model.
     *
     * @return bool True if the store is selected, false otherwise.
     */
    private function isStoreSelected($store)
    {
        // Compare the store ID with the request's store parameter
        return $store->getId() === Request::getParam('store');
    }

    /**
     * Generate a scope-specific hint for a given configuration path, scope type, and scope model.
     *
     * This method compares the configuration value for the given scope with the current
     * configuration value and generates a hint if they differ.
     *
     * @param string $path The configuration path.
     * @param string $scopeType The scope type (websites or stores).
     * @param WebsiteInterface|StoreInterface $scope The scope model (either website or store).
     *
     * @return string The scope hint, or an empty string if no difference is found.
     */
    private function getScopeHint($path, $scopeType, $scope)
    {
        // Initialize the scope line as an empty string
        $scopeLine = '';

        Config::setScope($scopeType);
        Config::setScopeId((int)$scope->getId());

        // Get the current and scope-specific configuration values
        $currentValue = Config::getString($path);
        $scopeValue = Config::getString($path);

        // If the scope value differs from the current value, generate the hint
        if ($scopeValue !== $currentValue) {
            switch ($scopeType) {
                case self::SCOPE_TYPE_STORES:
                    return __(
                        'Store <code>%1</code>: "%2"',
                        $scope->getCode(),
                        $scopeValue,
                    );

                case self::SCOPE_TYPE_WEBSITES:
                    return __(
                        'Website <code>%1</code>: "%2"',
                        $scope->getCode(),
                        $scopeValue,
                    );
            }
        }

        // Return an empty string if no scope-specific hint is required
        return $scopeLine;
    }
}
