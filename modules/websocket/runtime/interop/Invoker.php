<?php

const APP_ROOT = __DIR__ . '/../../../../';
const MODULES_ROOT = APP_ROOT . 'modules';
const ENGINE_ROOT = APP_ROOT . 'engine';

spl_autoload_register(function ($class_name) {

    $class_name = str_replace('alidation_helper', 'alidation', $class_name);
    $target_filename = realpath(ENGINE_ROOT . '/' . $class_name . '.php');

    if (file_exists($target_filename)) {
        return require_once($target_filename);
    }

    return false;
});

class Invoker
{
    public function __invoke(array $payload): Result {
        $handler = $payload['handler'] ?? 'controller';
        $handler = strtolower($handler);

        return $this->$handler($payload);
    }

    public function controller(array $payload): Result {
        $module = $payload['module'] ?? null;
        $controller = $payload['controller'] ?? ucwords($module);
        $action = $payload['action'] ?? '_on_websocket_message';
        
        $controller_path = MODULES_ROOT . '/' . $module . '/controllers/' . $controller . '.php';

        if (file_exists($controller_path) === false) {
            throw new InvalidArgumentException("Error: Controller [$controller] not found.");
        }

        require_once $controller_path;
        require_once __DIR__ . '/Result.php';
        
        $controllerInstance = new $controller($module);
        return new Result($controllerInstance->$action($payload, $client));
    }
}
