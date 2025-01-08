<?php

declare(strict_types=1);

namespace Maginium\ScopeHint\Interceptors;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\Eav;
use Magento\Framework\App\State as AppState;
use Magento\Framework\Registry;
use Maginium\Framework\Support\Facades\Json;
use Maginium\Framework\Support\Facades\StoreManager;
use Maginium\Framework\Support\Php;
use Throwable;

/**
 * Class ProductEavDataProviderPlugin.
 *
 * This plugin class modifies attribute meta data in the product EAV form to include scope hints in tooltips.
 * It runs only in developer mode and adds product attribute values specific to store views when applicable.
 */
class ProductEavDataProviderPlugin
{
    /**
     * @var Registry
     *
     * Registry object used to access and manage product data in the context of the current request.
     */
    private $registry;

    /**
     * @var ProductRepositoryInterface
     *
     * Product repository for fetching product data from the database.
     */
    private $productRepository;

    /**
     * @var array
     *
     * Stores the list of store views for optimization. Will be populated by getStores method.
     */
    private $stores;

    /**
     * @var AppState
     *
     * AppState object used to check the application's mode (developer mode).
     */
    private $appState;

    /**
     * ProductEavDataProviderPlugin constructor.
     *
     * @param AppState $appState
     * @param Registry $registry
     * @param ProductRepositoryInterface $productRepository
     *
     * Initializes dependencies required for this plugin, including the app state, registry, and product repository.
     */
    public function __construct(
        AppState $appState,
        Registry $registry,
        ProductRepositoryInterface $productRepository,
    ) {
        $this->appState = $appState;
        $this->registry = $registry;
        $this->productRepository = $productRepository;
    }

    /**
     * Plugin method that modifies attribute meta data to include scope hints in tooltips.
     *
     * This method is executed after the attribute meta data is set up. It checks if the application is in developer mode,
     * and if so, it appends scope-specific hints to the tooltip description for product attributes.
     * Scope-specific hints are added for each store view where the attribute value differs from the default value.
     *
     * @param Eav $subject The subject of the plugin (Eav modifier).
     * @param mixed $result The original result that is modified by this plugin.
     *
     * @return mixed The modified result with additional scope hints in the tooltip description.
     */
    public function afterSetupAttributeMeta(Eav $subject, $result)
    {
        // Ensure modification only happens in developer mode
        if ($this->appState->getMode() === AppState::MODE_DEVELOPER) {
            // Skip modification if certain conditions are met (e.g., globalScope or specific attribute code)
            if ($this->appState->getMode() !== AppState::MODE_DEVELOPER ||
                ! isset($result['arguments']['data']['config']['code']) ||
                $result['arguments']['data']['config']['globalScope'] ||
                $result['arguments']['data']['config']['code'] === 'quantity_and_stock_status'
            ) {
                return $result;
            }

            // Initialize an array to hold scope hints
            $scopeHints = [];

            // Get the attribute code
            $attributeCode = $result['arguments']['data']['config']['code'];

            // Retrieve store views
            $storeViews = $this->getStores();

            // Get the current product from the registry
            $product = $this->registry->registry('current_product');

            // Exit early if no product is found or the product is invalid
            if ($product === null || $product->getId() === null) {
                return $result;
            }

            // Iterate over each store view to gather scope-specific attribute values
            foreach ($storeViews as $storeView) {
                // Fetch product data specific to the current store view
                $productByStoreCode = $this->getProductInStoreView(
                    $product->getId(),
                    $storeView->getId(),
                );

                // Get the attribute value for the store view
                $currentScopeValueForCode = $value = $productByStoreCode->getData($attributeCode);

                // Attempt to convert the attribute value to a string, handling complex data types
                $valueAsString = null;

                try {
                    $valueAsString = (string)$value;
                } catch (Throwable $ex) {
                    // Fallback to JSON encoding if conversion fails
                    $valueAsString = Json::encode($value);

                    // If JSON encoding fails, set the value to null
                    if ($valueAsString === false) {
                        $valueAsString = null;
                    }
                }

                // If the value as a string is valid and differs from the default product value, add a scope hint
                if (
                    $valueAsString !== null &&
                    $product->getData($attributeCode) !== $currentScopeValueForCode
                ) {
                    // Add scope hint to the array
                    $scopeHints[] = $storeView->getName() . ': ' . $valueAsString;
                }

                // If scope hints are collected, update the tooltip description with the hints
                if (! empty($scopeHints)) {
                    $result['arguments']['data']['config']['tooltip']['description'] = Php::implode('<br>', $scopeHints);
                }
            }
        }

        // Return the modified result with added scope hints
        return $result;
    }

    /**
     * Retrieve product data for a specific store view.
     *
     * This method fetches the product data for the specified store view using the product repository.
     *
     * @param int $productId The ID of the product.
     * @param int $storeViewId The ID of the store view.
     *
     * @return mixed The product data for the specified store view.
     */
    private function getProductInStoreView($productId, $storeViewId)
    {
        // Fetch the product for the given product ID and store view ID
        return $this->productRepository->getById(
            $productId,
            false,
            $storeViewId,
        );
    }

    /**
     * Retrieve and cache the list of store views.
     *
     * This method checks if the list of store views is already cached. If not, it retrieves the list using the StoreManager.
     *
     * @return array The list of store views.
     */
    private function getStores()
    {
        // If the list of stores has not been cached, retrieve and cache it
        if (! $this->stores) {
            $this->stores = StoreManager::getStores();
        }

        // Return the cached list of stores
        return $this->stores;
    }
}
