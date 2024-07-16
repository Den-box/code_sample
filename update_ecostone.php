<?php
	set_time_limit(10000);
	define('DRUPAL_ROOT', getcwd());
	require_once DRUPAL_ROOT . '/includes/bootstrap.inc';
	drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);
	require('sites/all/libraries/simplehtmldom/simple_html_dom.php');
	$doc = array();
	
	$base = "https://ecostoneart.ru";
	$links = array("/catalog/artificial_stone/");
	
	$class_settings = 
	[
		"class_pages" => "a.catalogus",
		"class_desc" => ".tovarright",
		"class_specifications" => ".tovartable",
		"class_img" => ".tovarleft .galery_box",
		"class_a" => ".immmmm a",
		"reg_desc" => "/\<\/h2\>(.*)\<div/U",
		"reg_brief_desc" => "/(.*)\<br/U"
	]
	
	$term_settings = 
	[
		"vocabulary_name" => "stroeher",
		"parent" => "759",
		"taxonomy_term" => "760",
		"format" => "full_html",
		"taxonomy_img" => "gallery",
		"taxonomy_parent_img" => "761",
		"path_img" => "public://ecostone-photo/"
	];
	
	$node_settings = 
	[
		"node_type" => 'card_brick',
		"field_catalog" => "24",
		"field_water_absorption" => "6",
		"field_frost_resistance" => "F200",
		"field_manufacture" => "1930",
		"path_img" => "public://ecostone-photo/"
	];
	
	class Card
	{
		var $title;
		var $img;
		function __construct($im, $tit)
		{
			$this->img = $im;
			$this->title = $tit;
		}
	}
	
	class Page
	{
		var $hr;
		var $subtitle;
		var $title;
		var $full_title;
		var $desc;
		var $brief_desc;
		var $price;
		var $img;
		var $size;
		var $value;
		var $specifications;
		var $Cards;
	}
	
	function get_links($links, $Page)
	{
		$Pages = array($Page);
		foreach ($links as $key=>$value)
		{
			$page = file_get_html ($base.$value);
			foreach($page->find($class_for_pages) as $element)
			{
				$Pages[]->hr = $element->href;
			}
		}
		return $Pages;
	}
	
	function get_desc_card ($links_pages, $class_settings)
	{
		foreach ($links_pages as $value)
		{
			$page = file_get_contents($base.$value->hr);
			$page = iconv("cp1251", "UTF-8", $page);
			$page = str_get_html ($page);
			foreach($page->find('h1') as $element)
			{
				$parts = explode(' ', $element->innertext);
				$value->title = (isset($parts[5])) ? $parts[4]." ".$parts[5] : $parts[4];
				$value->subtitle = $parts[0]." ".$parts[1]." ".$parts[2]." ".$parts[3];
				$value->full_title = $element->innertext;
			}
			foreach($page->find($class_settings["class_desc"]) as $element)
			{
				preg_match($class_settings["reg_desc"], $element->outertext, $matches);
				$value->desc = $matches[1];
				preg_match($class_settings["reg_brief_desc"], $value->desc, $matches);
				$value->brief_desc = $matches[1];
			}
			$value->price = $page->find($class_for_specifications, 0)->children[12]->children[1]->innertext;
			foreach($page->find($class_for_specifications) as $element)
			{
				$element->children[3]->children[1]->innertext;
				$parts = explode('<br />', $element->children[3]->children[1]->innertext);
				$value->size = $parts[0];
			}
			$value->value = $page->find($class_settings["class_specifications"], 0)->children[6]->children[1]->innertext;
			$value->specifications = $page->find($class_settings["class_specifications"], 0)->outertext;
			foreach($page->find($class_settings["class_img"]) as $element)
				$value->img[] = $base.$element->href;
			foreach($page->find($class_settings["class_a"]) as $element)
				$value->Cards[] = new Card($base.$element->href, $element->title);
		}
		return $value;
	}
	
	function add_term($page, $term_settings)
	{
		$vocabulary = taxonomy_vocabulary_machine_name_load($term_settings["vocabulary_name"]);
		$term = array('name' => $page->full_title, 'vid' => $vocabulary->vid, 'parent' => $term_settings["parent"]);
		$tree = taxonomy_get_tree($vocabulary->vid, $parent = $term_settings["parent"], $max_depth = NULL, $load_entities = FALSE);
		foreach ($tree as $tree_item)
		{
			if ($tree_item->name == $page->full_title)
			{
				echo $tree_item->tid.". ".$tree_item->name." ";
				echo ". Вес: ".$page->value.". Цена: ".$page->price.".\r\n";
				$all_nodes = node_load_multiple(taxonomy_select_nodes($tree_item->tid, FALSE, FALSE));
				foreach ($all_nodes as $node)
				{
					echo $node->title;
					echo "Вес: ".$node->field_weight["und"][0]["value"];
					$node->field_weight["und"][0]["value"] = $page->value;
					echo "Цена: ".$node->field_price_m["und"][0]["value"].". (dom-klinkera174.ru)\r\n";
					$node->field_price_m["und"][0]["value"] = $page->price;
					node_save($node);
				}
				break;
			}
		}
		$vocabulary = taxonomy_vocabulary_machine_name_load($term_settings["vocabulary_name"]);
		$term = array('name' => $page->full_title, 'vid' => $vocabulary->vid, 'parent' => $term_settings["parent"]);
		$term = (object) $term;
		taxonomy_term_save($term);
		$tid = $term->tid;
		$tax = taxonomy_term_load($tid);
		$tax_old = taxonomy_term_load($term_settings["vocabulary_name"]);
		$tax->name = $page->full_title;
		$tax->description = $page->desc.$tax_old->description;
		$tax->format = "full_html";
		$tax->vocabulary_machine_name = $term_settings["taxonomy_term"];
		$tax->field_pdf = $tax_old->field_pdf;
		$tax->field_docs = $tax_old->field_docs;
		$tax->field_tech["und"][0]["value"] = $page->specifications;
		$tax->field_tech["und"][0]["format"] = $term_settings["format"];
        	$tax->field_tech["und"][0]["safe_value"] = $page->specifications;
		$tax->field_brief["und"][0]["value"] = "<h2>".$page->full_title."</h2><p>".$page->brief_desc."</p>";
		$tax->field_brief["und"][0]["format"] = $term_settings["format"];
		$tax->field_brief["und"][0]["safe_value"] = "<h2>".$page->full_title."</h2><p>".$page->brief_desc."</p>";
		taxonomy_term_save($tax);
		echo "Добавлен термин таксономии: ".$page->full_title."\r\n";
		return $tid;
	}
	
	function add_term_img($page, $term_settings)
	{
		$vocabulary_img = taxonomy_vocabulary_machine_name_load($term_settings["gallery"]);
		$term_img = array('name' => $object->sub_title." ".strtoupper($object->title), 'vid' => $vocabulary_img->vid, 'parent' => $term_settings["taxonomy_parent_img"]);
		$term_img = (object) $term_img;
		taxonomy_term_save($term_img);
		$tid_img = $term_img->tid;
		$tax_img = taxonomy_term_load($tid_img);
		$tax_img->name = $page->full_title;
		foreach($page->img as $img_n)
		{
			$img_url_n = $img_n;
			$file_temp_n = file_get_contents($img_n);
			$file_temp_n = file_save_data($file_temp_n, $term_settings["path_img"].basename($img_url_n), FILE_EXISTS_REPLACE);
			$tax_img->field_photo_taks[LANGUAGE_NONE][$i]['fid'] = $file_temp_n->fid;
			echo $img_n." - Загружено\r\n";
		}
		taxonomy_term_save($tax_img);
		echo "Загружены картинки: ".$page->full_title."\r\n";
		return $term_img;
	}
	
	function add_node($page, $tid, $tid_img)
	{
		$node = new stdClass();
		$node->type = $node_settings["node_type"];
		node_object_prepare($node);
		$node->language = LANGUAGE_NONE;
		$node->title = $card->title;
		$node->field_sub_title["und"][0]["value"] = $page->subtitle;
		$node->field_catalog["und"][0]["tid"] = $node_settings["field_catalog"];
		$node->field_full_desc["und"][0]["tid"] = $tid;
		$node->field_series_stroeher["und"][0]["tid"] = $tid;
		$node->field_weight["und"][0]["value"] = $page->value;
		$node->field_size["und"][0]["value"] = $page->size;
		$node->field_water_absorption["und"][0]["value"] = $node_settings["field_water_absorption"];
		$node->field_frost_resistance["und"][0]["value"] = $node_settings["field_frost_resistance"];
		$node->field_manufacture["und"][0]["value"] = $node_settings["field_manufacture"];
		$node->field_manufacture["und"][0]["revision_id"] = $node_settings["field_manufacture"];
		$node->field_price_m["und"][0]["value"] = $page->price;
		if(!empty($page->img))
		{
			$node->field_for_gal["und"][0]["tid"] = $tid_img;
		}
		$node->uid = 1;
		$node->status = 1;
		$node->promote = 1;
		$img_url = $card->img;
		$file_temp = file_get_contents($img_url);
		$file_temp = file_save_data($file_temp, $node_settings["path_img"].basename($img_url), FILE_EXISTS_REPLACE);
		$file_temp->fid;
		$node->field_img[LANGUAGE_NONE]['0']['fid'] = $file_temp->fid;
		return node_save($node);
	}
	
	function save_term_node ($desc_for_pages)
	{
		ob_start();
		foreach ($desc_for_pages as $page)
		{
			$tid = add_term($page, $term_settings);
			if(!empty($page->img))
			{
				$tid_img = add_term_img($page, $term_settings, $tid);
			}
			$k=0;
			foreach($page->Cards as $card)
			{
				if (add_node($page, $tid, $tid_img, $node_settings))
				echo "Добавлена позиция: ".$card->title."\r\n";
			}
		}
		$echos = ob_get_contents();
		ob_end_clean(); 
		file_put_contents("update_ecostone.txt", $echos);
	}

	$links_pages = get_links($links, $Page);
	$desc_for_pages = get_desc_card ($links_for_pages);
	array_shift($desc_for_pages);
	save_term_node ($desc_for_pages);
?>


