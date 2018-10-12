<?php

/**
 * ExpressionEngine - by EllisLab
 *
 * @package     ExpressionEngine
 * @author      ExpressionEngine Dev Team
 * @copyright   Copyright (c) 2003 - 2018, EllisLab, Inc.
 * @license     http://expressionengine.com/user_guide/license.html
 * @link        http://expressionengine.com
 * @since       Version 2.0
 * @filesource
 */

/**
 *
 * @package    ExpressionEngine
 * @subpackage Addons
 * @category   Module
 * @author     Ivan Koop
 * @link       https://vikm.co/
 */

require_once 'vendor/autoload.php';

use Elasticsearch\ClientBuilder;

class ResetElastic
{
    private $elastic_client = NULL;

    public function __construct()
	{

        $this->elastic_client = ClientBuilder::create()->build();

        $delete_params = [
            'index' => 'ee_search',
        ];

        try {
            $response = $this->elastic_client->indices()->delete($delete_params);

            if($response['acknowledged']) {
                error_log("SUCCESS --> " . "index deleted".PHP_EOL, 3, "error.log");
            }

        } catch (\Exception $e) {
            error_log("FATAL ERROR --> " . $e->getMessage().PHP_EOL, 3, "error.log");
        }
    }
}

$reset_elastic = new ResetElastic;
