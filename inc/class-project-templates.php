<?php
/**
 * Project templates for ReactifyWP
 *
 * @package ReactifyWP
 */

namespace ReactifyWP;

/**
 * Project Templates class
 */
class ProjectTemplates
{
    /**
     * Available templates
     */
    const TEMPLATES = [
        'basic-react' => [
            'name' => 'Basic React App',
            'description' => 'A simple React application template with basic structure',
            'files' => [
                'index.html' => '<!DOCTYPE html><html><head><title>React App</title></head><body><div id="root"></div><script src="./static/js/main.js"></script></body></html>',
                'static/js/main.js' => 'console.log("React app loaded");',
                'static/css/main.css' => 'body { margin: 0; font-family: Arial, sans-serif; }',
                'asset-manifest.json' => '{"files":{"main.js":"./static/js/main.js","main.css":"./static/css/main.css"},"entrypoints":["static/js/main.js","static/css/main.css"]}'
            ]
        ],
        'calculator' => [
            'name' => 'Calculator App',
            'description' => 'A functional calculator built with React',
            'files' => [
                'index.html' => '<!DOCTYPE html><html><head><title>Calculator</title><link href="./static/css/main.css" rel="stylesheet"></head><body><div id="root"></div><script src="./static/js/main.js"></script></body></html>',
                'static/js/main.js' => 'const Calculator=()=>{const[display,setDisplay]=React.useState("0");const[operation,setOperation]=React.useState(null);const[waitingForOperand,setWaitingForOperand]=React.useState(false);const calculate=(firstOperand,secondOperand,operation)=>{switch(operation){case"+":return firstOperand+secondOperand;case"-":return firstOperand-secondOperand;case"*":return firstOperand*secondOperand;case"/":return firstOperand/secondOperand;case"=":return secondOperand;default:return secondOperand}};const inputNumber=num=>{if(waitingForOperand){setDisplay(String(num));setWaitingForOperand(false)}else{setDisplay(display==="0"?String(num):display+num)}};const inputOperation=nextOperation=>{const inputValue=parseFloat(display);if(operation&&waitingForOperand){setOperation(nextOperation);return}if(operation){const currentValue=parseFloat(display);const newValue=calculate(inputValue,currentValue,operation);setDisplay(String(newValue));setOperation(nextOperation)}else{setOperation(nextOperation)}setWaitingForOperand(true)};const clear=()=>{setDisplay("0");setOperation(null);setWaitingForOperand(false)};return React.createElement("div",{className:"calculator"},React.createElement("div",{className:"display"},display),React.createElement("div",{className:"buttons"},React.createElement("button",{onClick:clear},"C"),React.createElement("button",{onClick:()=>inputOperation("/")},"÷"),React.createElement("button",{onClick:()=>inputOperation("*")},"×"),React.createElement("button",{onClick:()=>inputOperation("-")},"-"),React.createElement("button",{onClick:()=>inputNumber(7)},"7"),React.createElement("button",{onClick:()=>inputNumber(8)},"8"),React.createElement("button",{onClick:()=>inputNumber(9)},"9"),React.createElement("button",{onClick:()=>inputOperation("+"),className:"operator"},"+"),React.createElement("button",{onClick:()=>inputNumber(4)},"4"),React.createElement("button",{onClick:()=>inputNumber(5)},"5"),React.createElement("button",{onClick:()=>inputNumber(6)},"6"),React.createElement("button",{onClick:()=>inputNumber(1)},"1"),React.createElement("button",{onClick:()=>inputNumber(2)},"2"),React.createElement("button",{onClick:()=>inputNumber(3)},"3"),React.createElement("button",{onClick:()=>inputOperation("="),className:"equals"},"="),React.createElement("button",{onClick:()=>inputNumber(0),className:"zero"},"0"),React.createElement("button",{onClick:()=>setDisplay(display.includes(".")?display:display+".")},".")))};ReactDOM.render(React.createElement(Calculator),document.getElementById("root"));',
                'static/css/main.css' => '.calculator{max-width:300px;margin:50px auto;border:1px solid #ccc;border-radius:10px;overflow:hidden;box-shadow:0 4px 10px rgba(0,0,0,0.1)}.display{background:#000;color:#fff;font-size:2em;padding:20px;text-align:right;min-height:60px;display:flex;align-items:center;justify-content:flex-end}.buttons{display:grid;grid-template-columns:repeat(4,1fr);gap:1px;background:#ccc}.buttons button{border:none;background:#f9f9f9;padding:20px;font-size:1.2em;cursor:pointer;transition:background 0.2s}.buttons button:hover{background:#e9e9e9}.buttons button.operator{background:#ff9500;color:white}.buttons button.operator:hover{background:#e6850e}.buttons button.equals{background:#ff9500;color:white;grid-row:span 2}.buttons button.zero{grid-column:span 2}',
                'asset-manifest.json' => '{"files":{"main.js":"./static/js/main.js","main.css":"./static/css/main.css"},"entrypoints":["static/css/main.css","static/js/main.js"]}'
            ]
        ],
        'todo-list' => [
            'name' => 'Todo List App',
            'description' => 'A simple todo list application with add/remove functionality',
            'files' => [
                'index.html' => '<!DOCTYPE html><html><head><title>Todo List</title><link href="./static/css/main.css" rel="stylesheet"></head><body><div id="root"></div><script src="./static/js/main.js"></script></body></html>',
                'static/js/main.js' => 'const TodoApp=()=>{const[todos,setTodos]=React.useState([]);const[inputValue,setInputValue]=React.useState("");const addTodo=()=>{if(inputValue.trim()){setTodos([...todos,{id:Date.now(),text:inputValue.trim(),completed:false}]);setInputValue("")}};const removeTodo=id=>{setTodos(todos.filter(todo=>todo.id!==id))};const toggleTodo=id=>{setTodos(todos.map(todo=>todo.id===id?{...todo,completed:!todo.completed}:todo))};return React.createElement("div",{className:"todo-app"},React.createElement("h1",null,"Todo List"),React.createElement("div",{className:"input-section"},React.createElement("input",{type:"text",value:inputValue,onChange:e=>setInputValue(e.target.value),onKeyPress:e=>e.key==="Enter"&&addTodo(),placeholder:"Add a new todo..."}),React.createElement("button",{onClick:addTodo},"Add")),React.createElement("ul",{className:"todo-list"},todos.map(todo=>React.createElement("li",{key:todo.id,className:todo.completed?"completed":""},React.createElement("span",{onClick:()=>toggleTodo(todo.id)},todo.text),React.createElement("button",{onClick:()=>removeTodo(todo.id),className:"delete"},"×")))))};ReactDOM.render(React.createElement(TodoApp),document.getElementById("root"));',
                'static/css/main.css' => '.todo-app{max-width:500px;margin:50px auto;padding:20px;font-family:Arial,sans-serif}h1{text-align:center;color:#333;margin-bottom:30px}.input-section{display:flex;margin-bottom:20px;gap:10px}.input-section input{flex:1;padding:10px;border:1px solid #ddd;border-radius:4px;font-size:16px}.input-section button{padding:10px 20px;background:#007cba;color:white;border:none;border-radius:4px;cursor:pointer;font-size:16px}.input-section button:hover{background:#005a87}.todo-list{list-style:none;padding:0}.todo-list li{display:flex;justify-content:space-between;align-items:center;padding:10px;border:1px solid #eee;margin-bottom:5px;border-radius:4px;background:#f9f9f9}.todo-list li.completed{text-decoration:line-through;opacity:0.6}.todo-list li span{flex:1;cursor:pointer}.todo-list li .delete{background:#dc3232;color:white;border:none;border-radius:50%;width:25px;height:25px;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center}.todo-list li .delete:hover{background:#a00}',
                'asset-manifest.json' => '{"files":{"main.js":"./static/js/main.js","main.css":"./static/css/main.css"},"entrypoints":["static/css/main.css","static/js/main.js"]}'
            ]
        ]
    ];

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('wp_ajax_reactifywp_get_templates', [$this, 'handle_get_templates']);
        add_action('wp_ajax_reactifywp_create_from_template', [$this, 'handle_create_from_template']);
    }

    /**
     * Get available templates
     *
     * @return array Templates
     */
    public function get_templates()
    {
        $templates = [];
        
        foreach (self::TEMPLATES as $key => $template) {
            $templates[$key] = [
                'key' => $key,
                'name' => $template['name'],
                'description' => $template['description']
            ];
        }

        return apply_filters('reactifywp_project_templates', $templates);
    }

    /**
     * Create project from template
     *
     * @param string $template_key Template key
     * @param string $slug         Project slug
     * @param string $name         Project name
     * @return array|\WP_Error Project data or error
     */
    public function create_from_template($template_key, $slug, $name = '')
    {
        if (!isset(self::TEMPLATES[$template_key])) {
            return new \WP_Error('invalid_template', __('Invalid template.', 'reactifywp'));
        }

        $template = self::TEMPLATES[$template_key];
        $name = $name ?: $template['name'];

        // Check if project already exists
        $project = new Project();
        if ($project->get_by_slug($slug)) {
            return new \WP_Error('project_exists', __('A project with this slug already exists.', 'reactifywp'));
        }

        // Create project directory
        $upload_dir = wp_upload_dir();
        $blog_id = get_current_blog_id();
        $project_path = $upload_dir['basedir'] . '/reactify-projects/' . $blog_id . '/' . $slug;

        if (!wp_mkdir_p($project_path)) {
            return new \WP_Error('mkdir_failed', __('Failed to create project directory.', 'reactifywp'));
        }

        // Create template files
        foreach ($template['files'] as $file_path => $content) {
            $full_path = $project_path . '/' . $file_path;
            $dir = dirname($full_path);

            if (!wp_mkdir_p($dir)) {
                $this->remove_directory($project_path);
                return new \WP_Error('mkdir_failed', __('Failed to create file directory.', 'reactifywp'));
            }

            if (file_put_contents($full_path, $content) === false) {
                $this->remove_directory($project_path);
                return new \WP_Error('file_creation_failed', __('Failed to create template file.', 'reactifywp'));
            }
        }

        // Save project to database
        $project_data = [
            'slug' => $slug,
            'shortcode' => $slug,
            'project_name' => $name,
            'description' => $template['description'],
            'file_path' => $project_path,
            'file_size' => $this->calculate_directory_size($project_path),
            'version' => md5(time() . $slug),
            'status' => 'active'
        ];

        $save_result = $this->save_project($project_data);
        if (is_wp_error($save_result)) {
            $this->remove_directory($project_path);
            return $save_result;
        }

        // Get project ID and catalog assets
        $project_id = $this->get_project_id_by_slug($slug);
        $asset_manager = new AssetManager();
        $asset_manager->catalog_assets($project_path, $project_id);

        return $project_data;
    }

    /**
     * Handle get templates AJAX request
     */
    public function handle_get_templates()
    {
        check_ajax_referer('reactifywp_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'reactifywp'));
        }

        wp_send_json_success([
            'templates' => $this->get_templates()
        ]);
    }

    /**
     * Handle create from template AJAX request
     */
    public function handle_create_from_template()
    {
        check_ajax_referer('reactifywp_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'reactifywp'));
        }

        $template_key = sanitize_text_field($_POST['template'] ?? '');
        $slug = sanitize_text_field($_POST['slug'] ?? '');
        $name = sanitize_text_field($_POST['name'] ?? '');

        if (empty($template_key) || empty($slug)) {
            wp_send_json_error(__('Template and slug are required.', 'reactifywp'));
        }

        $result = $this->create_from_template($template_key, $slug, $name);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success([
            'message' => __('Project created from template successfully!', 'reactifywp'),
            'project' => $result
        ]);
    }

    /**
     * Save project to database
     *
     * @param array $project_data Project data
     * @return bool|\WP_Error True on success, error on failure
     */
    private function save_project($project_data)
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'reactify_projects';
        $blog_id = get_current_blog_id();
        
        $data = [
            'blog_id' => $blog_id,
            'slug' => $project_data['slug'],
            'shortcode' => $project_data['shortcode'],
            'project_name' => $project_data['project_name'],
            'description' => $project_data['description'] ?? '',
            'file_path' => $project_data['file_path'],
            'file_size' => $project_data['file_size'],
            'version' => $project_data['version'],
            'status' => $project_data['status'] ?? 'active',
            'settings' => json_encode([]),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];
        
        $result = $wpdb->insert($table_name, $data);
        
        if ($result === false) {
            return new \WP_Error('save_failed', __('Failed to save project to database.', 'reactifywp'));
        }
        
        return true;
    }

    /**
     * Get project ID by slug
     *
     * @param string $slug Project slug
     * @return int|null Project ID or null
     */
    private function get_project_id_by_slug($slug)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'reactify_projects';
        $blog_id = get_current_blog_id();

        return $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_name} WHERE blog_id = %d AND slug = %s",
            $blog_id,
            $slug
        ));
    }

    /**
     * Calculate directory size
     *
     * @param string $directory Directory path
     * @return int Size in bytes
     */
    private function calculate_directory_size($directory)
    {
        if (!is_dir($directory)) {
            return 0;
        }

        $size = 0;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        
        return $size;
    }

    /**
     * Remove directory recursively
     *
     * @param string $dir Directory path
     */
    private function remove_directory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            
            if (is_dir($path)) {
                $this->remove_directory($path);
            } else {
                unlink($path);
            }
        }
        
        rmdir($dir);
    }
}
