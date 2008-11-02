<?php defined('SYSPATH') or die('No direct script access.');

/**
 * This controller is used to manage users
 */
class Users_Controller extends Admin_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->template->this_page = 'users';
	}
	
	function index()
	{	
		$this->template->content = new View('admin/users');
		$this->template->content->title = 'Manage Users';
		$form = array
	    (
	        'user_id'   => '',
			'action'	=> '',
			'username' 	=> '',
			'password'  => '',
			'name'  	=> '',
			'email' 	=> '',
			'role'  	=> ''
	    );
		//  copy the form as errors, so the errors will be stored with keys corresponding to the form field names
	    $errors = $form;
		$form_error = FALSE;
		$form_saved = FALSE;
		$form_action = "";
		
		// check, has the form been submitted, if so, setup validation
	    if ($_POST)
	    {
	        $post = Validation::factory($_POST);
			
	         //  Add some filters
	        $post->pre_filter('trim', TRUE);
			
			if ($post->action = 'a') 				// Add/Edit Action
			{
	        	// Add some rules, the input field, followed by a list of checks, carried out in order
				$post->add_rules('username','required','length[3,16]', 'alpha');
			
				//only validate password as required when user_id has value.
				$post->user_id == ''? $post->add_rules('password','required',
				'length[5,16]','alpha_numeric'):'';
				$post->add_rules('name','required','length[3,100]');
			
				$post->add_rules('email','required','email','length[4,64]');
			
				$post->user_id == '' ? $post->add_callbacks('username',
					array($this,'username_exists_chk')) : '';
			
				$post->user_id == '' ? $post->add_callbacks('email',
					array($this,'email_exists_chk')) : '';
					
				// Validate for roles
				if ($post->role != 'admin' && $post->role != 'user') {
					$post->add_error('role', 'values');
				}
				
				// Prevent modification of the admin users role to user role
				if ($post->username == 'admin' && $post->role == 'user') {
					$post->add_error('username', 'admin');
				}
			}
			
			if ($post->validate())
	        {
				$user = ORM::factory('user',$post->user_id);
				if ($post->action = 'a') 				// Add/Edit Action
				{
					// Existing User??
					if ($user->loaded==true)
					{
						$user->username = $post->username;
						$user->name = $post->name;
						$user->email = $post->email;
						$post->password !='' ? $user->password=$post->password : '';
						$user->save();

						// Remove Old Roles
						foreach($user->roles as $role){
							$user->remove($role); 
						} 
						
						// Add New Role
						$user->add(ORM::factory('role', $post->role));
						
						$form_saved = TRUE;
						$form_action = "EDITED";
					}
					// New User
					else 
					{
						$user->username = $post->username;
						$user->name = $post->name;
						$user->password = $post->password;
						$user->email = $post->email;
						$user->save();
						
						// Add New Role
						$user->add(ORM::factory('role', $post->role));
						
						$form_saved = TRUE;
						$form_action = "ADDED";
					}
				}
				elseif ($post->action = 'd')			// Delete Action 
				{
					if ($user->loaded==true)
					{
						// Remove Roles
						foreach ($user->roles as $role) {
							$user->remove_role($role);
						}
						
						// Delete User
						$user->delete($post->user_id);
						$form_saved = TRUE;
						$form_action = "DELETED";
					}
				}
			} 
            else 
            {
				// repopulate the form fields
	            $form = arr::overwrite($form, $post->as_array());

	            // populate the error fields, if any
	            $errors = arr::overwrite($errors, $post->errors('auth'));
				$form_error = TRUE;
			}
	    }
		
		// Pagination
		$pagination = new Pagination(array(
			                'query_string' => 'page',
			                'items_per_page' => (int) Kohana::config('settings.items_per_page_admin'),
			                'total_items'  => ORM::factory('user')->count_all()
                        ));

		$users = ORM::factory('user')
                    ->orderby('name', 'asc')
                    ->find_all((int) Kohana::config('settings.items_per_page_admin'), 
                        $pagination->sql_offset);

        $this->template->content->form = $form;
	    $this->template->content->errors = $errors;
		$this->template->content->form_error = $form_error;
		$this->template->content->form_saved = $form_saved;
		$this->template->content->form_action = $form_action;
		$this->template->content->pagination = $pagination;
		$this->template->content->total_items = $pagination->total_items;
		$this->template->content->users = $users;
		$this->template->content->roles = array("admin"=>"admin","user"=>"user");
		
		// Javascript Header
		$this->template->colorpicker_enabled = TRUE;
		$this->template->js = new View('admin/users_js');
	}
	
	/**
	 * Checks if username already exists.
     * @param Validation $post $_POST variable with validation rules 
	 */
    //XXX: Should probably be marked private
	public function username_exists_chk(Validation $post)
	{
		$users = ORM::factory('user');
		// If add->rules validation found any errors, get me out of here!
		if (array_key_exists('username', $post->errors()))
			return;
				
		if( $users->username_exists($post->username) )
			$post->add_error( 'username', 'exists');
	}
	
	/**
	 * Checks if email address is associated with an account.
	 * @param Validation $post $_POST variable with validation rules 
	 */
    //XXX: Should probably be marked private
	public function email_exists_chk( Validation $post )
	{
		$users = ORM::factory('user');
		if( array_key_exists('email',$post->errors()))
			return;
			
		if( $users->email_exists( $post->email ) )
			$post->add_error('email','exists');
	}
}
