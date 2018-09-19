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

        $result_array = $query->get()->result_array();


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

            $this->elastic_client->index($params);

        }

    }

    public function do_search()
    {
        $this->insertToElastic();
        $this->like_input = isset($_GET['in']) ? $_GET['in'] : "";
        $this->cat_id = isset($_GET['c']) ? $_GET['c'] : null;


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

        $response = $this->elastic_client->search($params);
        echo "<pre>";
        print_r($response);
        echo "</pre>";


        foreach ($result as $value) {
            $entries_ids[] = $value['entry_id'];
            $this->output[$value['entry_id']] = $value;
            $this->output[$value['entry_id']]['categories'] = [];
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
