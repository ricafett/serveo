<?php

namespace App\Observers;

use App\Models\DocumentPrintConfig;
use App\Models\FulfillmentRoute;

class FulfillmentRouteObserver
{
    /**
     * When a new fulfillment route is created, auto-create a matching
     * DocumentPrintConfig for PRODUCTION_TICKET with safe defaults.
     */
    public function created(FulfillmentRoute $route): void
    {
        DocumentPrintConfig::firstOrCreate(
            ['document_type' => 'PRODUCTION_TICKET', 'fulfillment_route' => $route->code],
            ['group_items' => true, 'ignore_variants' => false, 'ignore_modifiers' => false, 'ignore_item_notes' => false, 'branding_header' => DocumentPrintConfig::defaultBrandingHeader()],
        );
    }

    /**
     * When a fulfillment route is deleted, remove orphan config rows.
     */
    public function deleted(FulfillmentRoute $route): void
    {
        DocumentPrintConfig::where('document_type', 'PRODUCTION_TICKET')
            ->where('fulfillment_route', $route->code)
            ->delete();
    }
}
