<?php

require_once 'vendor/autoload.php';

use Elasticsearch\ClientBuilder;

class Json_search
{

    private $limit = 500;
    private $output = [];
    private $like_input = "";
    private $cat_id = NULL;
    private $elastic_client = NULL;
    private $insert_to_elastic = "false";
    private $is_recursive_call = false;

    public function __construct()
	{
        $this->elastic_client = ClientBuilder::create()->build();

    }

    public function insertToElastic()
    {

        $query = ee()->db->select('exp_titles.entry_id,
                                        exp_titles.title,
                                        exp_desc.field_id_10 as description')
                        ->from('exp_channel_titles as exp_titles')
                        ->join('exp_channel_data_field_10 exp_desc','exp_titles.entry_id = exp_desc.entry_id');


        try {
            $result_array = $query->get()->result_array();
        } catch (\Exception $e) {
            error_log("FATAL ERROR MYSQL --> " . $e->getMessage().PHP_EOL, 3, "error.log");
        }

        error_log("INSERTING DATA  --> " . "Inserting data to elastic".PHP_EOL, 3, "error.log");
        foreach($result_array as $result) {

            $params = [
                'index' => 'ee_search',
                'type' => 'producto_entry',
                'id' => $result['entry_id'],
                'body' => [
                    'title' => $result['title'],
                    'description' => $result['description']
                ]
            ];

            $response = $this->elastic_client->index($params);

        }

    }

    public function do_search($recursive = false)
    {

        $this->like_input = isset($_GET['in']) ? $_GET['in'] : "";
        $this->cat_id = isset($_GET['c']) ? $_GET['c'] : null;
        $this->insert_to_elastic = isset($_GET['insert']) ? $_GET['insert'] : "";

        if($this->insert_to_elastic == "true") {
            $this->insertToElastic(); exit;
        }

        $params = [
            'index' => 'ee_search',
            'type' => 'producto_entry',
            'body' => [
                'query' => [
                    'multi_match' => [
                        'fields' => ['title','description'],
                        'query' => $this->like_input,
                        'fuzziness' => "2"
                    ]
                ]
            ]
        ];

        try {
            $response = $this->elastic_client->search($params);
        } catch (\Exception $e) {

            error_log("INDEX NOT FOUND  --> " . $e->getMessage().PHP_EOL, 3, "error.log");

            if(!$recursive) {

                $this->insertToElastic();

                //giving elastic some time to insert the data
                sleep(1);

                $this->do_search(true);

            } else {
                error_log("RECURSIVE CALL ERROR --> " . "do_search() recursive error".PHP_EOL, 3, "error.log");
            }

        }


        $es_result = $response['hits']['hits'];



        foreach ($es_result as $value) {
            $entries_ids[] = $value['_id'];
            $this->output[$value['_id']] = $value;
            $this->output[$value['_id']]['categories'] = [];
        }

        $categories_query = ee()->db->select('exp_post.entry_id, exp_cat.cat_id,exp_cat.parent_id')
                                    ->from('exp_category_posts as exp_post')
			                        ->join('exp_categories exp_cat', 'exp_cat.cat_id = exp_post.cat_id')
			                        ->where_in('exp_post.entry_id', $entries_ids);

        $categories_result = $categories_query->get()->result_array();

        foreach($categories_result as $cat_value) {
            array_push($this->output[$cat_value['entry_id']]['categories'],$cat_value);
        }


        if($this->cat_id == null) {

            $this->output();
        }

        $ids_to_remove = [];
        foreach($this->output as $key => $value) {

            $has_cat = false;
            $count_cat = count($value['categories']);

            for ($i = 0; $i < $count_cat; $i++) {

                if($this->cat_id == $value['categories'][$i]['cat_id']) {
                    $has_cat = true;
                }

                if($i == ($count_cat -1) && !$has_cat) {
                    array_push($ids_to_remove, $key);
                }
            }
        }

        $this->output = array_diff_key($this->output, array_flip($ids_to_remove));
        $this->output();

    }

    public function output() {
        header('Content-Type: application/json');
		echo json_encode($this->output);
		exit;
    }
}
