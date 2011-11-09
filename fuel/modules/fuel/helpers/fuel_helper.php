<?php 
/**
 * FUEL CMS
 * http://www.getfuelcms.com
 *
 * An open source Content Management System based on the 
 * Codeigniter framework (http://codeigniter.com)
 *
 * @package		FUEL CMS
 * @author		David McReynolds @ Daylight Studio
 * @copyright	Copyright (c) 2011, Run for Daylight LLC.
 * @license		http://www.getfuelcms.com/user_guide/general/license
 * @link		http://www.getfuelcms.com
 * @filesource
 */

// ------------------------------------------------------------------------

/**
 * FUEL Helper
 *
 * @package		FUEL CMS
 * @subpackage	Libraries
 * @category	Libraries
 * @author		David McReynolds @ Daylight Studio
 * @link		http://www.getfuelcms.com/user_guide/helpers/fuel_helper
 */

// --------------------------------------------------------------------

/**
 * Returns the instance of the FUEL object
 *
 * @access	public
 * @param	mixed
 * @return	string
 */
function &fuel_instance()
{
	return Fuel::get_instance();
}

function &FUEL()
{
	$f = & fuel_instance();
	return $f;
}

// --------------------------------------------------------------------

/**
 * Allows you to load a view and pass data to it
 *
 * @access	public
 * @param	mixed
 * @return	string
 */
function fuel_block($params)
{
	$CI =& get_instance();
	$CI->load->library('parser');
	
	$valid = array( 'view' => '',
					'view_string' => FALSE,
					'model' => '', 
					'find' => 'all',
					'select' => NULL,
					'where' => '', 
					'order' => '', 
					'limit' => NULL, 
					'offset' => 0, 
					'return_method' => 'auto', 
					'assoc_key' => '',
					'data' => array(),
					'editable' => TRUE,
					'parse' => 'auto',
					'vars' => array(),
					'cache' => FALSE,
					);

	// for convenience
	if (!is_array($params))
	{
		$new_params = array();
		if (strpos($params, '=') === FALSE)
		{
			$new_params['view'] = $params;
		}
		else
		{
			$CI->load->helper('array');
			$new_params = parse_string_to_array($params);
		}
		$params = $new_params;
	}

	$p = array();
	foreach($valid as $param => $default)
	{
		$p[$param] = (isset($params[$param])) ? $params[$param] : $default;
	}
	
	// pull from cache if cache is TRUE and it exists
	if ($p['cache'] === TRUE)
	{
		$CI->load->library('cache');
		$cache_group = $CI->fuel->config('page_cache_group');
		$cache_id = (!empty($p['view_string'])) ? $p['view_string'] : $p['view'];
		$cache_id = md5($cache_id);
		$cache = $CI->cache->get($cache_id, $cache_group);
		if (!empty($cache))
		{
			return $cache;
		}
	}
	
	// load the model and data
	$vars = (array) $p['vars'];
	if (!empty($p['model']))
	{
		$data = fuel_model($p['model'], $p);
		$module_info = $CI->fuel_modules->info($p['model']);
		if (!empty($module_info))
		{
			$var_name = $CI->$module_info['model_name']->short_name(TRUE, FALSE);
			$vars[$var_name] = $data;
		}
	}
	else
	{
		$vars['data'] = $p['data'];
	}
	
	$output = '';
	
	// load proper view to parse. If a view is given then we first look up the name in the DB
	$view = '';
	if (!empty($p['view_string']))
	{
		$view = $p['view_string'];
	}
	else if (!empty($p['view']))
	{
		$view_file = APPPATH.'views/_blocks/'.$p['view'].EXT;
		if ($CI->fuel->config('fuel_mode') != 'views')
		{
			$CI->load->module_model(FUEL_FOLDER, 'blocks_model');
			
			// find the block in FUEL db
			$block = $CI->blocks_model->find_one_by_name($p['view']);
			if (isset($block->id))
			{
				if (strtolower($p['parse']) == 'auto')
				{
					$p['parse'] = TRUE;
				}

				$view = $block->view;
				
				if ($p['editable'] === TRUE)
				{
					$view = fuel_edit($block->id, 'Edit Block: '.$block->name, 'blocks').$view;
				}
			}
			else if (file_exists(APPPATH.'views/_blocks/'.$p['view'].EXT))
			{
				// pass in reference to global CI object
				$vars['CI'] =& get_instance();
				
				// pass along these since we know them... perhaps the view can use them
				$view = $CI->load->view("_blocks/".$p['view'], $vars, TRUE);
			}
		}
		else if (file_exists($view_file))
		{
			// pass along these since we know them... perhaps the view can use them
			$view = $CI->load->view("_blocks/".$p['view'], $vars, TRUE);
		}
	}
	
	// parse the view again to apply any variables from previous parse
	$output = ($p['parse'] === TRUE) ? $CI->parser->parse_string($view, $vars, TRUE) : $view;
	
	if ($p['cache'] === TRUE)
	{
		$CI->cache->save($cache_id, $output, $cache_group, $CI->fuel->config('page_cache_ttl'));
	}
	
	return $output;
}


// --------------------------------------------------------------------

/**
 * Loads a module model and creates a variable in the view that you can use to merge data 
 *
 * @access	public
 * @param	string
 * @param	mixed
 * @return	string
 */
function fuel_model($model, $params = array())
{
	$CI =& get_instance();
	$CI->load->module_library(FUEL_FOLDER, 'fuel_modules');
	$valid = array( 'find' => 'all',
					'select' => NULL,
					'where' => array(), 
					'order' => '', 
					'limit' => NULL, 
					'offset' => 0, 
					'return_method' => 'auto', 
					'assoc_key' => '',
					'var' => '',
					'module' => ''
					);
					
	if (!is_array($params))
	{
		$CI->load->helper('array');
		$params = parse_string_to_array($params);
	}

	foreach($valid as $p => $default)
	{
		$$p = (isset($params[$p])) ? $params[$p] : $default;
	}

	// load the model
	$mod = $CI->fuel->modules->get($model);
	if (empty($mod)) return NULL;
	
	$module_info = $mod->info();
	$model_name = $module_info['model_name'];

	// return NULL if model_name is empty
	if (empty($model_name)) return NULL;

	//echo $model_name;
	if (!empty($module))
	{
		$CI->load->module_model($module, $model_name);
	}
	else
	{
		$CI->load->model($model_name);
	}
	
	 // to get around escapinng issues we need to add spaces after =
	if (is_string($where))
	{
		$where = preg_replace('#([^>|<|!])=#', '$1 = ', $where);
	}
	
	// run select statement before the find
	if (!empty($select))
	{
		$CI->$model_name->db()->select($select, FALSE);
	}
	
	// retrieve data based on the method
	if ($find === 'key')
	{
		$data = $CI->$model_name->find_by_key($where, $return_method);
		$var = $CI->$model_name->short_name(TRUE, TRUE);
	}
	else if ($find === 'one')
	{
		$data = $CI->$model_name->find_one($where, $order, $return_method);
		$var = $CI->$model_name->short_name(TRUE, TRUE);
	}
	else
	{
		if (empty($find) OR $find == 'all')
		{
			$data = $CI->$model_name->find_all($where, $order, $limit, $offset, $return_method, $assoc_key);
			$var = $CI->$model_name->short_name(TRUE, FALSE);
		}
		else
		{
			$method = 'find_'.$find;
			if (method_exists($CI->$model_name, $method))
			{
				if (!empty($where)) $CI->$model_name->db()->where($where);
				if (!empty($order)) $CI->$model_name->db()->order_by($order);
				if (!empty($offset)) $CI->$model_name->db()->offset($offset);
				$data = $CI->$model_name->$method($where, $order, $limit, $offset);
				if (is_array($data) AND key($data) === 0)
				{
					$var = $CI->$model_name->short_name(TRUE, FALSE);
				}
				else
				{
					$var = $CI->$model_name->short_name(TRUE, TRUE);
				}
			}

		}
	}

	$vars[$var] = $data;
	
	// load the variable for the view to use
	$CI->load->vars($vars);
	
	// set the model to readonly so no data manipulation can't occur
	$CI->$model_name->readonly = TRUE;
	return $data;
}

// --------------------------------------------------------------------

/**
 * Creates a menu structure
 *
 * @access	public
 * @param	mixed
 * @return	string
 */
function fuel_nav($params = array())
{
	$CI =& get_instance();
	return $CI->fuel->navigation->menu($params);
}

// --------------------------------------------------------------------

/**
 * Generates a page using the Fuel_page class
 *
 * @access	public
 * @param	string
 * @param	array
 * @param	array
 * @return	string
 */
function fuel_page($location, $vars = array(), $params = array())
{
	$CI =& get_instance();
	$output = $CI->fuel->pages->render($location, $vars, $params, TRUE);
	return $output;
}

// --------------------------------------------------------------------

/**
 * Creates a form using form builder
 *
 * @access	public
 * @param	array
 * @param	array
 * @param	array
 * @return	string
 */
function fuel_form($fields, $values = array(), $params = array())
{
	$CI =& get_instance();
	$CI->load->library('form_builder', $params);
	$CI->form_builder->set_fields($fields);
	$CI->form_builder->set_field_values($values);
	return $CI->form_builder->render();
}


// --------------------------------------------------------------------

/**
 * Sets a variable for all views to use no matter what view it is declared in
 *
 * @access	public
 * @param	string
 * @param	array
 * @return	string
 */
function fuel_set_var($key, $val = NULL)
{
	$CI =& get_instance();
	if (is_array($key))
	{
		$CI->load->vars($key);
	}
	else if (is_string($key))
	{
		$vars[$key] = $val;
		$CI->load->vars($vars);
	}
}

// --------------------------------------------------------------------

/**
 * Appends a value to an array variable
 *
 * @access	public
 * @param	string
 * @param	mixed
 * @return	void
 */
function fuel_var_append($key, $value)
{
	$CI =& get_instance();
	$vars = $CI->load->get_vars();
	if (isset($vars[$key]) AND is_array($vars[$key]))
	{
		if (is_array($value))
		{
			$vars[$key] = array_merge($vars[$key], $value);
		}
		else
		{
			array_push($vars[$key], $value);
		}
		fuel_set_var($key, $vars[$key]);
	}
}

// --------------------------------------------------------------------

/**
 * Returns a variable and allows for a default value
 *
 * @access	public
 * @param	string
 * @param	string
 * @param	string
 * @param	boolean
 * @return	string
 */
function fuel_var($key, $default = '', $edit_module = 'pagevariables', $evaluate = TRUE)
{
	$CI =& get_instance();
	$CI->load->helper('inflector');
	if (isset($GLOBALS[$key]))
	{
		$val = $GLOBALS[$key];
	}
	else if ($CI->load->get_var($key))
	{
		$val = $CI->load->get_var($key);
	}
	else
	{
		$val = $default;
	}
	
	if (is_string($val) AND $evaluate)
	{
		$val = eval_string($val);
	}
	else if (is_array($val) AND $evaluate)
	{
		foreach($val as $k => $v)
		{
			$val[$k] = eval_string($v);
		}
	}
	
	if ($edit_module === TRUE) $edit_module = 'pagevariables';
	if (!empty($edit_module) AND $CI->fuel->config('fuel_mode') != 'views' AND !defined('USE_FUEL_MARKERS') OR (defined('USE_FUEL_MARKERS') AND USE_FUEL_MARKERS))
	{
		$marker = fuel_edit($key, humanize($key), $edit_module);
	}
	else
	{
		$marker = '';
	}
	
	if (is_string($val))
	{
		return $marker.$val;
	}
	else
	{
		if (!empty($marker))
		{
			// used to help with javascript positioning
			$marker = '<span>'.$marker.'</span>';
		}
		return $marker;
	}
}

// --------------------------------------------------------------------

/**
 * Sets a variable marker in a layout which can be used in editing mode
 *
 * @access	public
 * @param	mixed
 * @param	string
 * @param	string
 * @param	int
 * @param	int
 * @return	string
 */
function fuel_edit($id, $label = NULL, $module = 'pagevariables', $xoffset = NULL, $yoffset = NULL)
{
	$CI =& get_instance();
	$page = $CI->fuel->pages->active();
	if (empty($page))
	{
		$page = $CI->fuel->pages->create();
	}
	if (!empty($id) AND (!defined('FUELIFY') OR defined('FUELIFY') AND FUELIFY !== FALSE))
	{
		$marker['id'] = $id;
		$marker['label'] = $label;
		$marker['module'] = $module;
		$marker['xoffset'] = $xoffset;
		$marker['yoffset'] = $yoffset;

		$key = $page->add_marker($marker);

		return '<!--'.$key.'-->';
	}
	return '';
}

// --------------------------------------------------------------------

/**
 * Creates the cache ID for the FUEL page based on the URI
 *
 * @access	public
 * @param	string
 * @return	string
 */
function fuel_cache_id($location = NULL)
{
	if (empty($location))
	{
		$CI =& get_instance();
		$segs = $CI->uri->segment_array();
	
		if (empty($segs)) 
		{
			return 'home';
		}
		return implode('.', $segs);
	}
	return str_replace('/', '.', $location);
}

// --------------------------------------------------------------------

/**
 * Creates the admin URL for FUEL (e.g. http://localhost/MY_PROJECT/fuel/admin)
 *
 * @access	public
 * @param	string
 * @return	string
 */
function fuel_url($uri = '')
{
	$CI =& get_instance();
	return site_url($CI->fuel->config('fuel_path').$uri);
}

// --------------------------------------------------------------------

/**
 * Returns the FUEL admin URI path
 *
 * @access	public
 * @param	string
 * @return	string
 */
function fuel_uri($uri = '')
{
	$CI =& get_instance();
	return $CI->fuel->config('fuel_path').$uri;
}

// --------------------------------------------------------------------

/**
 * Returns the uri segment based on the FUEL admin path
 *
 * @access	public
 * @param	int
 * @param	boolean
 * @return	string
 */
function fuel_uri_segment($seg_index = 0, $rerouted = FALSE)
{
	$CI =& get_instance();
	if ($rerouted)
	{
		return $CI->uri->rsegment(fuel_uri_index($seg_index));
	}
	else
	{
		return $CI->uri->segment(fuel_uri_index($seg_index));
	}
}

// --------------------------------------------------------------------

/**
 * Returns the uri index number based on the FUEL admin path
 *
 * @access	public
 * @param	int
 * @return	int
 */
function fuel_uri_index($seg_index = 0)
{
	$CI =& get_instance();
	$fuel_path = $CI->fuel->config('fuel_path');
	$start_index = count(explode('/', $fuel_path)) - 1;
	return $start_index + $seg_index;
}

// --------------------------------------------------------------------

/**
 * Returns the uri string based on the FUEL admin path
 *
 * @access	public
 * @param	int
 * @param	int
 * @param	boolean
 * @return	string
 */
function fuel_uri_string($from = 0, $to = NULL, $rerouted = FALSE)
{
	$CI =& get_instance();
	$fuel_index = fuel_uri_index($from);
	
	if ($rerouted)
	{
		$segs = $CI->uri->rsegment_array($fuel_index);
	}
	else
	{
		$segs = $CI->uri->segment_array($fuel_index);
	}
	$from = fuel_uri_index($from);
	if (isset($to)) {
		$to = fuel_uri_index($to);
		if ($from < $to)
		{
			$to = $from;
		}
	}
	$segs = array_slice($segs, $from, $to);
	$uri = implode('/', $segs);
	return $uri;
}

// --------------------------------------------------------------------

/**
 * Check to see if you are logged in and can use inline editing
 *
 * @access	public
 * @return	booleanocal
 */
function is_fuelified()
{
	$CI =& get_instance();
	$CI->load->helper('cookie');
	return (get_cookie($CI->fuel->auth->get_fuel_trigger_cookie_name()));
}

// --------------------------------------------------------------------

/**
 * Returns the user language of the person logged in... used for inline editing
 *
 * @access	public
 * @return	booleanocal
 */
function fuel_user_lang()
{
	$CI =& get_instance();
	$CI->load->helper('cookie');
	$cookie_val = get_cookie($CI->fuel->auth->get_fuel_trigger_cookie_name());
	$cookie_val = unserialize($cookie_val);
	if (empty($cookie_val['language']))
	{
		$cookie_val['language'] = $CI->config->item('language');
	}
	return $cookie_val['language'];
}

// --------------------------------------------------------------------

/**
 * Returns HTML for publish/active fields in the list view
 *
 * @access	public
 * @return	string
 */
function fuel_unpublish_func($cols)
{   
        $CI = & get_instance();
        $can_publish = $CI->fuel_auth->has_permission($CI->permission, "publish");
        $is_publish = (isset($cols['published'])) ? TRUE : FALSE;
        $no = lang("form_enum_option_no");
        $yes = lang("form_enum_option_yes");            

        if ((isset($cols['published']) AND $cols['published'] == "no") OR (isset($cols['active']) AND $cols['active'] == "no"))
        {
            $text_class = ($can_publish) ? "publish_text unpublished toggle_publish" : "unpublished";
            $action_class = ($can_publish) ? "publish_action unpublished hidden" : "unpublished hidden";
            $col_txt = ($is_publish) ? 'click to publish' : 'click to activate';
            return '<span class="publish_hover"><span class="".$text_class."" id="row_published_".$cols["' . $CI->model->key_field() . '"]."">".$no."</span><span class="".$action_class."">".$col_txt."</span></span>';
        }
        else if ((isset($cols['published']) AND $cols['published'] == "yes") OR (isset($cols['active']) AND $cols['active'] == "yes"))
        {
            $text_class = ($can_publish) ? "publish_text published toggle_unpublish" : "published";
            $action_class = ($can_publish) ? "publish_action published hidden" : "published hidden";
            $col_txt = ($is_publish) ? 'click to unpublish' : 'click to deactivate';
            return '<span class="publish_hover"><span class="'.$text_class.'" id="row_published_'.$cols[$CI->model->key_field()].'">'.$yes.'</span><span class="'.$action_class.'">'.$col_txt.'</span></span>';
        }
}
