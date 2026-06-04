<?php
/**
 * Base REST API Controller for Microstock Metadata Addon.
 *
 * @package AIdoforyouMetadata
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class AIDOFORYOU_Metadata_Endpoint_Base extends AIDOFORYOU_Core_Endpoint_Base {

    protected AIDOFORYOU_Gemini_Client $client;

    public function __construct( AIDOFORYOU_Gemini_Client $client ) {
        // Suntikkan Credit Manager dari Core Plugin
        parent::__construct( aidoforyou()->credits );
        $this->client = $client;
    }
}