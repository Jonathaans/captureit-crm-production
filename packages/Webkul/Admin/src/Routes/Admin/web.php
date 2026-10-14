<?php

/**
 * Auth routes.
 */
require 'auth-routes.php';

/**
 * Leads routes.
 */
require 'leads-routes.php';

/**
 * Email routes.
 */
require 'mail-routes.php';

/**
 * Settings routes.
 */
require 'settings-routes.php';

/**
 * Products routes.
 */
require 'products-routes.php';

/**
 * Contacts routes.
 */
require 'contacts-routes.php';

/**
 * Activities routes.
 */
require 'activities-routes.php';

/**
 * Quotes routes.
 */
require 'quote-routes.php';

require __DIR__.'/invoice-routes.php';
require __DIR__.'/historical-document-routes.php';
/**
 * Delivery Order routes.
 */
require __DIR__.'/delivery-order-routes.php';

/**
 * Inventory routes.
 */
require __DIR__.'/inventory-routes.php';

/**
 * Configuration routes.
 */
require 'configuration-routes.php';

/**
 * Help routes.
 */
require 'help-routes.php';

/**
 * Rest routes.
 */
require 'rest-routes.php';
