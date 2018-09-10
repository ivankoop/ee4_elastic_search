<?php if( ! defined('BASEPATH')) exit('No direct script access allowed');

class Json_search
{
    private $limit = 500;
    private $output = [];
    private $like_input = "";
    private $cat_id = null;

    public function __construct()
	{
        $this->like_input = $_GET['in'];
        $this->cat_id = $_GET['c'];
    }

    public function do_search()
    {

        $like_query = ee()->db->select('entry_id, title')
                                ->from('exp_channel_titles')
                                ->where_not_in('status','closed')
                                ->where('expiration_date >', strval(time()))
                                ->or_where('expiration_date', '0')
                                ->like('title', $this->like_input)
                                ->order_by('entry_date','DESC')
                                ->limit($this->limit);


        $result = $like_query->get()->result_array();


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
