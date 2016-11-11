<?php
//定义基本函数
/**
 * 判断目录是否存在，如果不存在则创建
 * @param $path
 */
function mk_dir($path)
{
    if (!is_dir($path)) {
        mkdir($path, 0744);
        echo "INFO: creating directory: {$path} ..." . PHP_EOL;
    }
}

/**
 * 扫描某个路径下的所有文件，并拷贝到目标路径下
 * @param $src_path
 * @param $tar_path
 */
function copy_files($src_path, $tar_path)
{
    mk_dir($tar_path);

    $files = scandir($src_path);
    foreach ($files as $file) {
        $src_file_name = $src_path . '/' . $file;
        $tar_file_name = $tar_path . '/' . $file;
        if (is_file($src_file_name)) {
            copy($src_file_name, $tar_file_name);
            echo "INFO: copying " . $file . "\n";
        }
    }
}

/**
 * 处理模板并写入模板文件的函数
 * @param string $path 模板目标目录
 * @param array $module 目标
 * @param array $index 模板索引
 * @param array $model 模型
 * @param string $namespace 命名空间
 * @param array $templates 模板数组
 * @param string $suffix 文件后缀名
 */
function write_template_file($path, $module, $index, $model, $namespace, $templates, $suffix = '.php')
{
    mk_dir($path);

    switch ($suffix) {
        case '.php':
            write_php($path, $module, $index, $model, $namespace, $templates);
            break;
        case '.sql':
            write_sql($path, $index, $model, $namespace, $templates);
            break;
        case '.html':
            write_html($path, $module, $index, $model, $templates);
            break;
    }
}

/**
 * 生成 html 代码
 * @param $path
 * @param $module
 * @param $index
 * @param $model
 * @param $templates
 */
function write_html($path, $module, $index, $model, $templates)
{
    global $defaults;
    mk_dir($path);

    $content = str_replace('{{MODEL_NAME}}', strtolower($model['name']), $templates['view_' . $index['name']]);
    $content = str_replace('{{MODULE_NAME}}', strtolower($module['name']), $content);
    $content = isset($module['comment']) ? str_replace('{{MODULE_COMMENT}}', $module['comment'], $content) : $content;
    $content = str_replace('{{MODEL_COMMENT}}', $model['comment'], $content);
    $content = str_replace('{{ACTION_COMMENT}}', $index['comment'], $content);

    //处理模型的字段
    if (isset($model['fields'])) {
        $fields = $model['fields'];
        if ($index['name'] == 'index') {
            $_tr = '<th>ID</th>' . "\n";
            $_td = '<td>{$it.id}</td>' . "\n";
            foreach ($fields as $field) {
                $_tr .= '<th>' . $field['title'] . '</th>' . "\n";
                $_td .= '<td>{$it.' . $field['name'] . '}</td>' . "\n";
            }
            $content = str_replace('{{TR_LOOP}}', $_tr, $content);
            $content = str_replace('{{TD_LOOP}}', $_td, $content);
        } else {
            foreach ($fields as $field) {
                $content_field = $templates['view_' . $index['name'] . '_field'];
                $content_field = str_replace('{{FORM_FIELD}}', getFieldHTML($field, $index['name']), $content_field);
                $content_field = str_replace('{{FIELD_NAME}}', $field['name'], $content_field);
                $content_field = str_replace('{{FIELD_TITLE}}', $field['title'], $content_field);
                if (isset($field['rule'])) {
                    //判断是否是需要生成选择列表的外键
                    if (preg_match('/_id$/', $field['name'])) {
                        $_comment = '请选择';
                    } else {
                        $_comment = '请输入';
                    }
                    $_comment .= $field['title'] . '，必须是' . $defaults['rules'][$field['rule']];
                } else {
                    $_comment = '';
                }
                $content_field = str_replace('{{FIELD_COMMENT}}', $_comment, $content_field);

                if (isset($field['required'])) $_is_required = ($field['required']) ? '（* 必须）' : '';
                else $_is_required = '';
                $content_field = str_replace('{{IS_REQUIRED}}', $_is_required, $content_field);

                $content = str_replace('{{FIELD_LOOP}}', $content_field . "\n{{FIELD_LOOP}}", $content);
            }
            $content = str_replace('{{FIELD_LOOP}}', '', $content);
        }
    }
    $_file = $path . '/' . strtolower($index['name']) . '.html';

    file_put_contents($_file, $content);
    echo "INFO: writing {$index['name']}: {$_file} ..." . PHP_EOL;
}

/**
 * 生成 sql 代码
 * @param $path
 * @param $index
 * @param $model
 * @param $namespace
 * @param $templates
 */
function write_sql($path, $index, $model, $namespace, $templates)
{
    mk_dir($path);

    $_class_name = $model['name'];
    $content = str_replace('{{APP_NAME}}', $namespace, $templates[$index['name']]);
    $content = isset($model['name']) ? str_replace('{{MODEL_NAME}}', strtolower($model['name']), $content) : $content;
    $content = isset($model['comment']) ? str_replace('{{MODEL_COMMENT}}', $model['comment'], $content) : $content;

    //处理SQL的字段
    $content_field = '';
    if (isset($model['fields'])) {
        $fields = $model['fields'];
        foreach ($fields as $field) {
            $content_field = '  ' . getFieldSQL($field);
            $content_field = str_replace('{{FIELD_NAME}}', $field['name'], $content_field);
            $content_field = str_replace('{{FIELD_TITLE}}', $field['title'], $content_field);
            $content = str_replace('{{FIELD_LOOP}}', $content_field . "\n{{FIELD_LOOP}}", $content);
        }
        $content = str_replace('{{FIELD_LOOP}}', '', $content);
    }
    $content = str_replace('{{CONTROLLER_PARAMS}}', $content_field, $content);
    $content = str_replace('{{CLASS_NAME}}', $_class_name, $content);

    $_file = $path . '/' . strtolower($model['name']) . '.sql';

    file_put_contents($_file, $content);
    echo "INFO: writing {$index['name']}: {$_file} ..." . PHP_EOL;
}

/**
 * 生成 php 代码
 * @param $path
 * @param $module
 * @param $index
 * @param $model
 * @param $namespace
 * @param $templates
 */
function write_php($path, $module, $index, $model, $namespace, $templates)
{
    global $defaults;
    mk_dir($path);

    $_namespace = $namespace . '\\' . $module['name'] . '\\' . $index['name'];
    $_class_name = $model['name'];
    $content = str_replace('{{NAME_SPACE}}', $_namespace, $templates[$index['name']]);
    $content = isset($module['comment']) ? str_replace('{{MODULE_COMMENT}}', $module['comment'], $content) : $content;
    $content = isset($model['name']) ? str_replace('{{APP_NAME}}', $model['name'], $content) : $content;
    $content = isset($model['name']) ? str_replace('{{MODEL_NAME}}', $model['name'], $content) : $content;
    $content = isset($model['comment']) ? str_replace('{{MODEL_COMMENT}}', $model['comment'], $content) : $content;
    $content = str_replace('{{APP_PATH}}', APP_PATH, $content);

    //处理与控制器相关的模板
    if ($index['name'] == 'controller') {
        //处理控制器的方法
        if (isset($model['actions'])) {
            $actions = $model['actions'];
            foreach ($actions as $action) {
                $content_action = $templates['controller_action'];
                $content_action = str_replace('{{ACTION_NAME}}', $action['name'], $content_action);
                $content_action = str_replace('{{ACTION_COMMENT}}', $action['comment'], $content_action);

                $content = str_replace('{{CONTROLLER_ACTIONS}}', $content_action . "\n{{CONTROLLER_ACTIONS}}", $content);
            }
        }
        $content = str_replace('{{CONTROLLER_ACTIONS}}', '', $content);

        //处理控制器的参数
        $content_field = '';
        if (isset($model['fields'])) {
            $fields = $model['fields'];
            foreach ($fields as $field) {
                $content_field .= '$model->' . $field['name'] . " = input('" . $field['name'] . "');\n";
            }
        }
        $content = str_replace('{{CONTROLLER_PARAMS}}', $content_field, $content);
    }

    //处理校验器相关的模板
    if ($index['name'] == 'validate') {
        $content_field = '';
        if (isset($model['fields'])) {
            $fields = $model['fields'];
            foreach ($fields as $field) {
                $content_field .= PHP_EOL . "\t\t['" . $field['name'] . "', '";
                $content_field .= $field['required'] ? 'require|' : '';
                $content_field .= $field['rule'] . '\',\'';
                $content_field .= $field['required'] ? '必须输入：' . $field['title'] . '|' : '';
                $content_field .= $defaults['rules'][$field['rule']];
                $content_field .= '\'],';
            }
            $content = str_replace('{{VALIDATE_FIELDS}}', $content_field . "\n{{VALIDATE_FIELDS}}", $content);
        }
        $content = str_replace(",\n{{VALIDATE_FIELDS}}", "\n\t", $content);
    }

    $content = str_replace('{{CLASS_NAME}}', $_class_name, $content);
    $_file = $path . '/' . $model['name'] . '.php';

    file_put_contents($_file, $content);
    echo "INFO: writing {$index['name']}: {$_file} ..." . PHP_EOL;
}

/**
 * 写入 nginx 配置文件
 * @param $path
 * @param $template
 * @param $domain
 * @param $applications
 */
function write_nginx($path, $template, $domain, $applications)
{
    $content = str_replace('{{DOMAIN}}', $domain, $template);
    foreach ($applications as $application) {
        $content_temp = "\t\t\trewrite ^(.*)$ /" . $application['portal'] . ".php/$1 last;" . PHP_EOL . "{{REWRITE_LOOP}}";
        $content = str_replace('{{REWRITE_LOOP}}', $content_temp, $content);
    }
    $content = str_replace("\n{{REWRITE_LOOP}}", '', $content);
    $_file = $path . 'nginx_vhost';
    file_put_contents($_file, $content);
    echo "INFO: writing nginx profile: {$_file} ..." . PHP_EOL;
}

/**
 * 写入 nginx 配置文件
 * @param $path
 * @param $template
 * @param $applications
 */
function write_apache($path, $template, $applications)
{
    $content = $template;
    foreach ($applications as $application) {
        $content_temp = "  RewriteRule ^(.*)$ " . $application['portal'] . ".php/$1 [QSA,PT,L]" . PHP_EOL . "{{REWRITE_LOOP}}";
        $content = str_replace('{{REWRITE_LOOP}}', $content_temp, $content);
    }
    $content = str_replace("\n{{REWRITE_LOOP}}", '', $content);
    $_file = $path . '.htaccess';
    file_put_contents($_file, $content);
    echo "INFO: writing apache htaccess profile: {$_file} ..." . PHP_EOL;
}

/**
 * 判断字段的类型和参数，来生成不同类型的参数字段html
 * @param $field
 * @param string $action
 * @return string
 */
function getFieldHTML($field, $action = 'add')
{
    if ($field['rule'] == 'boolean') {
        if ($action == 'add') return "<input type=\"checkbox\" class=\"md-check\" id=\"{{FIELD_NAME}}\" name=\"{{FIELD_NAME}}\">";
        else  return "<input type=\"checkbox\" class=\"md-check\" id=\"{{FIELD_NAME}}\" name=\"{{FIELD_NAME}}\" value=\"{\$it.{{FIELD_NAME}}}\">";
    }

    if ($field['rule'] == 'email') {
        if ($action == 'add') return "<span class=\"input-group-addon\"><i class=\"fa fa-envelope\"></i></span><input type=\"checkbox\" class=\"md-check\" id=\"{{FIELD_NAME}}\" name=\"{{FIELD_NAME}}\"><div class=\"form-control-focus\"></div>";
        else  return "<span class=\"input-group-addon\"><i class=\"fa fa-envelope\"></i></span><input type=\"checkbox\" class=\"md-check\" id=\"{{FIELD_NAME}}\" name=\"{{FIELD_NAME}}\" value=\"{\$it.{{FIELD_NAME}}}\"><div class=\"form-control-focus\"></div>";
    }

    if ($field['rule'] == 'text') {
        if ($action == 'add') return "<textarea rows=\"4\" id=\"{{FIELD_NAME}}\" name=\"{{FIELD_NAME}}\"></textarea><div class=\"form-control-focus\"></div>";
        else  return "<textarea rows=\"4\" id=\"{{FIELD_NAME}}\" name=\"{{FIELD_NAME}}\">{\$it.{{FIELD_NAME}}}</textarea><div class=\"form-control-focus\"></div>";
    }

    if (preg_match('/_id$/', $field['name'])) {
        $_model = str_replace('_id', '', $field['name']);
        if ($action == 'add') return "<select class=\"form-control edited\" id=\"{{FIELD_NAME}}\" name=\"{{FIELD_NAME}}\">{volist name=\"" . $_model . "List\" id=\"it\"}<option value=\"{\$it.id}\">{\$it.name}</option>{/volist}</select> ";
        else return "<select class=\"form-control edited\" id=\"{{FIELD_NAME}}\" name=\"{{FIELD_NAME}}\">{volist name=\"" . $_model . "List\" id=\"it\"}<option value=\"{\$it.id}\">{\$it.name}</option>{/volist}</select> ";
    }
    if ($action == 'add') return "<input type=\"text\" class=\"form-control\" id=\"{{FIELD_NAME}}\" name=\"{{FIELD_NAME}}\">";
    else  return "<input type=\"text\" class=\"form-control\" id=\"{{FIELD_NAME}}\" name=\"{{FIELD_NAME}}\" value=\"{\$it.{{FIELD_NAME}}}\">";
}

/**
 * 判断字段的类型和参数，生成不同类型的字段 sq
 * @param $field
 * @return string
 */
function getFieldSQL($field)
{
    if (preg_match('/_id$/', $field['name'])) {
        return '`{{FIELD_NAME}}` bigint(20) NOT NULL COMMENT \'{{FIELD_TITLE}}\',';
    }

    if (preg_match('/^is_/', $field['name'])) {
        return '`{{FIELD_NAME}}` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'{{FIELD_TITLE}}\',';
    }

    if ($field['rule'] == 'datetime') {
        return '`{{FIELD_NAME}}` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT \'{{FIELD_TITLE}}\',';
    }

    if ($field['rule'] == 'text') {
        return '`{{FIELD_NAME}}` TEXT COMMENT \'{{FIELD_TITLE}}\',';
    }

    return '`{{FIELD_NAME}}` varchar(100) NOT NULL COMMENT \'{{FIELD_TITLE}}\',';
}
