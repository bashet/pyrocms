<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Regular pages model
 *
 * @author		Phil Sturgeon
 * @author		PyroCMS Dev Team
 * @package		PyroCMS\Core\Modules\Pages\Models
 *
 */
class Page_m extends MY_Model
{

	/**
	 * Array containing the validation rules
	 * @access public
	 * @var array
	 */
	public $validate = array(
		array(
			'field' => 'title',
			'label'	=> 'lang:pages.title_label',
			'rules'	=> 'trim|required|max_length[250]'
		),
		'slug' => array(
			'field' => 'slug',
			'label'	=> 'lang:pages.slug_label',
			'rules'	=> 'trim|required|alpha_dot_dash|max_length[250]|callback__check_slug'
		),
		array(
			'field' => 'chunk_body[]',
			'label'	=> 'lang:pages.body_label',
			'rules' => 'trim'
		),
		array(
			'field' => 'layout_id',
			'label'	=> 'lang:pages.layout_id_label',
			'rules'	=> 'trim|numeric|required'
		),
		array(
			'field'	=> 'css',
			'label'	=> 'lang:pages.css_label',
			'rules'	=> 'trim'
		),
		array(
			'field'	=> 'js',
			'label'	=> 'lang:pages.js_label',
			'rules'	=> 'trim'
		),
		array(
			'field' => 'meta_title',
			'label' => 'lang:pages.meta_title_label',
			'rules' => 'trim|max_length[250]'
		),
		array(
			'field'	=> 'meta_keywords',
			'label' => 'lang:pages.meta_keywords_label',
			'rules' => 'trim|max_length[250]'
		),
		array(
			'field'	=> 'meta_description',
			'label'	=> 'lang:pages.meta_description_label',
			'rules'	=> 'trim'
		),
		array(
			'field' => 'restricted_to[]',
			'label'	=> 'lang:pages.access_label',
			'rules'	=> 'trim|numeric|required'
		),
		array(
			'field' => 'rss_enabled',
			'label'	=> 'lang:pages.rss_enabled_label',
			'rules'	=> 'trim|numeric'
		),
		array(
			'field' => 'comments_enabled',
			'label'	=> 'lang:pages.comments_enabled_label',
			'rules'	=> 'trim|numeric'
		),
		array(
			'field' => 'is_home',
			'label'	=> 'lang:pages.is_home_label',
			'rules'	=> 'trim|numeric'
		),
		array(
			'field'	=> 'status',
			'label'	=> 'lang:pages.status_label',
			'rules'	=> 'trim|alpha|required'
		),
		array(
			'field' => 'navigation_group_id',
			'label' => 'lang:pages.navigation_label',
			'rules' => 'numeric'
		)
	);

	/**
	* Get a page by it's path
	*
	* @access public
	* @param array $segments The path segments
	* @return array
	*/
	/*
	* Not in use right now but added back for a) historical purposes and b) it was f**king difficult to write and I dont want to have to do it again
	*
	public function get_by_path($segments = array())
	{
	// If the URI has been passed as a string, explode to create an array of segments
	if(is_string($segments))
	{
		$segments = explode('/', $segments);
	}

	// Work out how many segments there are
	$total_segments = count($segments);

		// Which is the target alias (the final page in the tree)
	$target_alias = 'p'.$total_segments;

	// Start Query, Select (*) from Target Alias, from Pages
	$this->db->select($target_alias.'.*, revisions.id as revision_id, revisions.owner_id, revisions.table_name, revisions.body, revisions.revision_date, revisions.author_id');
	$this->db->from('pages p1');

		// Simple join enables revisions - Yorick
		$this->db->join('revisions', 'p1.revision_id = revisions.id');

	// Loop thorugh each Slug
	$level = 1;
	foreach( $segments as $segment )
	{
	    // Current is the current page, child is the next page to join on.
	    $current_alias = 'p'.$level;
	    $child_alias = 'p'.($level - 1);

	    // We dont want to join the first page again
	    if($level != 1)
	    {
		$this->db->join('pages '.$current_alias, $current_alias.'.parent_id = '.$child_alias.'.id');
	    }

	    // Add slug to where clause to keep us on the right tree
	    $this->db->where($current_alias . '.slug', $segment);

	    // Increment
	    ++$level;
	}

	// Can only be one result
	$this->db->limit(1);

	return $this->db->get()->row();
	}
	*/

	/**
	 * Get a page by it's URI
	 *
	 * @access public
	 * @param string	The uri of the page
	 * @param bool		Is this an http request or called from a plugin
	 * @return object
	 */
	public function get_by_uri($uri, $is_request = FALSE)
	{
		// If the URI has been passed as a array, implode to create a string of uri segments
		is_array($uri) && $uri = trim(implode('/', $uri), '/');

		// $uri gets shortened so we save the original
		$original_uri = $uri;
		$page = FALSE;
		$i = 0;

		while ( ! $page AND $uri AND $i < 15) /* max of 15 in case it all goes wrong (this shouldn't ever be used) */
		{
			$page = $this->db
				->where('uri', $uri)
				->limit(1)
				->get('pages')
				->row();

			// if it's not a normal page load (plugin or etc. that is not cached)
			// then we won't do our recursive search
			if ( ! $is_request)
			{
				break;
			}

			// if we didn't find a page with that exact uri AND there's more than one segment
			if ( ! $page AND strpos($uri, '/') !== FALSE)
			{
				// pop the last segment off and we'll try again
				$uri = preg_replace('@^(.+)/(.*?)$@', '$1', $uri);
			}
			// we didn't find a page and there's only one segment; it's going to 404
			elseif ( ! $page)
			{
				break;
			}
			$i++;
		}

		if ($page)
		{
			// so we found a page but if strict uri matching is required and the unmodified
			// uri doesn't match the page we fetched then we pretend it didn't happen
			if ($is_request AND (bool) $page->strict_uri AND $original_uri !== $uri)
			{
				return FALSE;
			}

			// things like breadcrumbs need to know the actual uri, not the uri with extra segments
			$page->base_uri = $uri;
		}

		return $page;
	}

	/**
	* Get the home page
	*
	* @access public
	* @param string  The uri of the page
	* @return object
	*/
	public function get_home()
	{
		return $this->db
			->where('is_home', 1)
			->get('pages')
			->row();
	}

	/**
	 * Build a multi-array of parent > children.
	 *
	 * @author Jerel Unruh - PyroCMS Dev Team
	 * @access public
	 * @return array An array representing the page tree
	 */
	public function get_page_tree()
	{
		$all_pages = $this->db
			->select('id, parent_id, title')
			 ->order_by('`order`')
			 ->get('pages')
			 ->result_array();

		// we must reindex the array first
		foreach ($all_pages as $row)
		{
			$pages[$row['id']] = $row;
		}

		unset($all_pages);

		// build a multidimensional array of parent > children
		foreach ($pages as $row)
		{
			if (array_key_exists($row['parent_id'], $pages))
			{
				// add this page to the children array of the parent page
				$pages[$row['parent_id']]['children'][] =& $pages[$row['id']];
			}

			// this is a root page
			if ($row['parent_id'] == 0)
			{
				$page_array[] =& $pages[$row['id']];
			}
		}

		return $page_array;
	}

	/**
	 * Set the parent > child relations and child order
	 *
	 * @author Jerel Unruh - PyroCMS Dev Team
	 * @param array $page
	 * @return void
	 */
	public function _set_children($page)
	{
		if (isset($page['children']))
		{
			foreach ($page['children'] as $i => $child)
			{
				$this->db->where('id', str_replace('page_', '', $child['id']));
				$this->db->update('pages', array('parent_id' => str_replace('page_', '', $page['id']), '`order`' => $i));

				//repeat as long as there are children
				if (isset($child['children']))
				{
					$this->_set_children($child);
				}
			}
		}
	}

	/**
	 * Does the page have children?
	 *
	 * @access public
	 * @param int $parent_id The ID of the parent page
	 * @return mixed
	 */
	public function has_children($parent_id)
	{
		return parent::count_by(array('parent_id' => $parent_id)) > 0;
	}

	/**
	 * Get the child IDs
	 *
	 * @param int $id The ID of the page?
	 * @param array $id_array ?
	 * @return array
	 */
	public function get_descendant_ids($id, $id_array = array())
	{
		$id_array[] = $id;

		$children = $this->db->select('id, title')
			->where('parent_id', $id)
			->get('pages')->result();

		$has_children = !empty($children);

		if ($has_children)
		{
			// Loop through all of the children and run this function again
			foreach ($children as $child)
			{
				$id_array = $this->get_descendant_ids($child->id, $id_array);
			}
		}

		return $id_array;
	}

	/**
	 * Build a lookup
	 *
	 * @access public
	 * @param int $id
	 * @return array
	 */
	public function build_lookup($id)
	{
		$current_id = $id;

		$segments = array();
		do
		{
			$page = $this->db
				->select('slug, parent_id')
				->where('id', $current_id)
				->get('pages')
				->row();

			$current_id = $page->parent_id;
			array_unshift($segments, $page->slug);
		}
		while( $page->parent_id > 0 );

		// If the URI has been passed as a string, explode to create an array of segments
		return parent::update($id, array(
			'uri' => implode('/', $segments)
		));
	}

	/**
	 * Reindex child items
	 *
	 * @access public
	 * @param int $id The ID of the parent item
	 * @return void
	 */
	public function reindex_descendants($id)
	{
		$descendants = $this->get_descendant_ids($id);
		foreach ($descendants as $descendant)
		{
			$this->build_lookup($descendant);
		}
	}

	/**
	 * Update lookup for entire page tree
	 * used to update page uri after ajax sort
	 *
	 * @access public
	 * @param array $root_pages An array of top level pages
	 * @return void
	 */
	public function update_lookup($root_pages)
	{
		// first reset the URI of all root pages
		$this->db
			->where('parent_id', 0)
			->set('uri', 'slug', FALSE)
			->update('pages');

		foreach ($root_pages as $page)
		{
			$this->reindex_descendants($page);
		}
	}

	/**
	 * Create a new page
	 *
	 * @access 	public
	 * @param 	array 	$input The sanitized $_POST
	 * @return 	bool
	 */
	public function create($input)
	{
		$this->db->trans_start();

		if ( ! empty($input['is_home']))
		{
			// Remove other homepages so this one can have the spot
			$this->where('is_home', 1)
				->update('pages', array('is_home' => 0));
		}

		// validate the data and insert it if it passes
		$input['id'] = $this->insert(array(
			'slug'				=> $input['slug'],
			'title'				=> $input['title'],
			'uri'				=> NULL,
			'parent_id'			=> (int) $input['parent_id'],
			'layout_id'			=> (int) $input['layout_id'],
			'css'				=> isset($input['css']) ? $input['css'] : null,
			'js'				=> isset($input['js']) ? $input['js'] : null,
			'meta_title'    	=> isset($input['meta_title']) ? $input['meta_title'] : '',
			'meta_keywords' 	=> isset($input['meta_keywords']) ? $input['meta_keywords'] : '',
			'meta_description' 	=> isset($input['meta_description']) ? $input['meta_description'] : '',
			'rss_enabled'		=> (int) ! empty($input['rss_enabled']),
			'comments_enabled'	=> (int) ! empty($input['comments_enabled']),
			'status'			=> $input['status'],
			'created_on'		=> now(),
			'restricted_to'		=> isset($input['restricted_to']) ? implode(',', $input['restricted_to']) : '0',
			'strict_uri'		=> (int) ! empty($input['strict_uri']),
			'is_home'			=> (int) ! empty($input['is_home']),
			'order'				=> now()
		));

		// did it pass validation?
		if ( ! $input['id']) return FALSE;

		$this->build_lookup($input['id']);

		// now insert this page's chunks
		$this->page_chunk_m->create($input);

		// Add a Navigation Link
		if ($input['navigation_group_id'] > 0)
		{
			$this->load->model('navigation/navigation_m');
			$this->navigation_m->insert_link(array(
				'title'					=> $input['title'],
				'link_type'				=> 'page',
				'page_id'				=> $input['id'],
				'navigation_group_id'	=> (int) $input['navigation_group_id']
			));
		}

		$this->db->trans_complete();

		return ($this->db->trans_status() === FALSE) ? FALSE : $input;
	}

	/**
	* Update a Page
	 *
	 * @access public
	 * @param int $id The ID of the page to update
	 * @param array $input The data to update
	 * @return void
	*/
	public function update($id = 0, $input = array(), $chunks = array())
	{
		$this->db->trans_start();

		if ( ! empty($input['is_home']))
		{
			// Remove other homepages
			$this->db
				->where('is_home', 1)
				->update($this->_table, array('is_home' => 0));
		}

		parent::update($id, array(
			'title'				=> $input['title'],
			'slug'				=> $input['slug'],
			'uri'				=> NULL,
			'parent_id'			=> $input['parent_id'],
			'layout_id'			=> $input['layout_id'],
			'css'				=> $input['css'],
			'js'				=> $input['js'],
			'meta_title'		=> $input['meta_title'],
			'meta_keywords'		=> $input['meta_keywords'],
			'meta_description'	=> $input['meta_description'],
			'restricted_to'		=> $input['restricted_to'],
			'rss_enabled'		=> (int) ! empty($input['rss_enabled']),
			'comments_enabled'	=> (int) ! empty($input['comments_enabled']),
			'strict_uri'		=> (int) ! empty($input['strict_uri']),
			'is_home'			=> (int) ! empty($input['is_home']),
			'status'			=> $input['status'],
			'updated_on'		=> now()
		));

		$this->build_lookup($id);

		if ($chunks)
		{
			// Remove the old chunks
			$this->db->delete('page_chunks', array('page_id' => $id));

			// And add the new ones
			$i = 1;
			foreach ($chunks as $chunk)
			{
				$this->db->insert('page_chunks', array(
					'page_id' 	=> $id,
					'sort' 		=> $i++,
					'slug' 		=> preg_replace('/[^a-zA-Z0-9_-\s]/', '', $chunk->slug),
					'body' 		=> $chunk->body,
					'type' 		=> $chunk->type,
					'parsed'	=> ($chunk->type == 'markdown') ? parse_markdown($chunk->body) : ''
				));
			}
		}
		// Wipe cache for this model, the content has changd
		$this->pyrocache->delete_all('page_m');
		$this->pyrocache->delete_all('navigation_m');

		$this->db->trans_complete();

		return ($this->db->trans_status() === FALSE) ? FALSE : TRUE;
	}

	/**
	* Delete a Page
	 *
	 * @access public
	 * @param int $id The ID of the page to delete
	 * @return bool
	*/
	public function delete($id = 0)
	{
		$this->db->trans_start();

		$ids = $this->get_descendant_ids($id);

		$this->db->where_in('id', $ids);
		$this->db->delete('pages');

		$this->db->where_in('page_id', $ids);
		$this->db->delete('navigation_links');

		$this->db->trans_complete();

		return $this->db->trans_status() !== FALSE ? $ids : FALSE;
	}

	/**
	 * Check Slug for Uniqueness
	 * @access public
	 * @param slug, parent id, this records id
	 * @return bool
	*/
	public function check_slug($slug, $parent_id, $id = 0)
	{
		return (int) parent::count_by(array('id !='	=>	$id,
											'slug'	=>	$slug,
											'parent_id' => $parent_id
											)
									  ) > 0;
	}

	/**
	 * Callback to check uniqueness of slug + parent
	 *
	 * @access public
	 * @param $slug slug to check
	 * @return bool
	 */
	 public function _check_slug($slug, $page_id = null)
	 {
		if ($this->check_slug($slug, $this->input->post('parent_id'), (int) $page_id))
		{
			if ($this->input->post('parent_id') == 0)
			{
				$parent_folder = lang('pages_root_folder');
				$url = '/'.$slug;
			}
			else
			{
				$page_obj = $this->get($page_id);
				$url = '/'.trim(dirname($page_obj->uri),'.').$slug;
				$page_obj = $this->get($this->input->post('parent_id'));
				$parent_folder = $page_obj->title;
			}

			$this->form_validation->set_message('_check_slug',sprintf(lang('pages_page_already_exist_error'),$url, $parent_folder));
			return FALSE;
		}

		// We check the page chunk slug length here too
		if (is_array($this->input->post('chunk_slug')))
		{
			foreach ($this->input->post('chunk_slug') AS $chunk)
			{
				if (strlen($chunk) > 30)
				{
					$this->form_validation->set_message('_check_slug', lang('pages_chunk_slug_length'));
					return FALSE;
				}
			}
			return TRUE;
		}
	}
}