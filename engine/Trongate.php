<?php
class Trongate {

    use Dynamic_properties;

    private ?Model $model;
    protected ?string $module_name = '';
    protected string $parent_module = '';
    protected string $child_module = '';

    /**
     * Constructor for Trongate class.
     * 
     * @param string|null $module_name The name of the module to use, or null for default module.
     */
    public function __construct(?string $module_name = null) {
        $this->module_name = $module_name;
    }

    /**
     * Loads a template controller file, instantiates the corresponding object, and calls
     * the specified template method with the given data.
     *
     * @param string $template_name The name of the template method to call.
     * @param array $data The data to pass to the template method.
     *
     * @return void
     *
     * @throws Exception If the template controller file cannot be found or the template method does not exist.
     * 
     * @see https://trongate.io/docs/information/what-are-templates
     */
    public function template(string $template_name, array $data = []): void {
        $template_controller_path = '../templates/controllers/Templates.php';
        require_once $template_controller_path;

        $templates = new Templates;

        if (method_exists($templates, $template_name)) {

            if (!isset($data['view_file'])) {
                $data['view_file'] = DEFAULT_METHOD;
            }

            $templates->$template_name($data);
        } else {
            $template_controller_path = str_replace('../', APPPATH, $template_controller_path);
            die('ERROR: Unable to find ' . $template_name . ' method in ' . $template_controller_path . '.');
        }
    }

    /**
     * Loads a module using the Modules class.
     *
     * This method serves as an alternative way of invoking the load method from the Modules class.
     * It simply instantiates a Modules object and calls its load method with the provided target module name.
     *
     * @param string $target_module The name of the target module.
     * @return void
     */
    public function module(string $target_module): void {
        $modules = new Modules;
        $modules->load($target_module);
    }

    /**
     * Upload a picture file using the upload method from the Image class.
     *
     * This method serves as an alternative way of invoking the upload method from the Image class.
     * It simply instantiates an Image object and calls its upload method with the provided configuration data.
     *
     * @param array $config The configuration data for handling the upload.
     * @return array|null The information of the uploaded file.
     */
    public function upload_picture(array $config): ?array {
        $image = new Image;
        return $image->upload($config);
    }

    /**
     * Upload a file using the upload method from the File class.
     *
     * This method serves as an alternative way of invoking the upload method from the File class.
     * It simply instantiates a File object and calls its upload method with the provided configuration data.
     *
     * @param array $config The configuration data for handling the upload.
     * @return array|null The information of the uploaded file.
     */
    public function upload_file(array $config): ?array {
        $file = new File;
        return $file->upload($config);
    }

    /**
     * Renders a view and returns the output as a string, or to the browser.
     *
     * @param  string     $view The name of the view file to render.
     * @param  array      $data An array of data to pass to the view file.
     * @param  bool|null  $return_as_str If set to true, the output is returned as a string, otherwise to the browser.
     *
     * @return string|null If $return_as_str is true, returns the output as a string, otherwise returns null.
     * @throws \Exception
     * 
     * @see https://trongate.io/docs/information/understanding-view-files
     */
    protected function view(string $view, array $data = [], ?bool $return_as_str = null): ?string {
        $return_as_str = $return_as_str ?? false;
        $module_name = $data['view_module'] ?? $this->module_name;

        $view_path = $this->get_view_path($view, $module_name);
        extract($data);

        if ($return_as_str) {
            // Output as string
            ob_start();
            require $view_path;
            return ob_get_clean();
        } else {
            // Output view file
            require $view_path;
            return null;
        }
    }

    /**
     * Get the path of a view file.
     *
     * @param string $view The name of the view file.
     * @param string $module_name Module name to which the view belongs.
     *
     * @return string The path of the view file.
     * @throws \Exception If the view file does not exist.
     */
    private function get_view_path(string $view, ?string $module_name): string {

        if ($this->parent_module !== '' && $this->child_module !== '') {
            // Load view from child module
            $view_path = APPPATH . "modules/$this->parent_module/$this->child_module/views/$view.php";
        } else {
            // Normal view loading process
            $view_path = APPPATH . "modules/$module_name/views/$view.php";
        }

        if (file_exists($view_path)) {
            return $view_path;
        } else {
            $error_message = $this->parent_module !== '' && $this->child_module !== '' ?
                "View '$view_path' does not exist for child view" :
                "View '$view_path' does not exist";
            throw new Exception($error_message);
        }
    }

}