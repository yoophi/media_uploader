<?php
App::uses('AppController', 'Controller');

/**
 * Attachments Controller
 *
 */
class AttachmentsController extends AppController
{

    /**
     * Scaffold
     *
     * @var mixed
     */
    public $scaffold;

    // public $autoRender = false;
    // public $UploadHandler = null;

    // protected $options;
    public $options;
    // PHP File Upload error message codes:
    // http://php.net/manual/en/features.file-upload.errors.php
    protected $error_messages = array(
        1 => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
        2 => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
        3 => 'The uploaded file was only partially uploaded',
        4 => 'No file was uploaded',
        6 => 'Missing a temporary folder',
        7 => 'Failed to write file to disk',
        8 => 'A PHP extension stopped the file upload',
        'max_file_size' => 'File is too big',
        'min_file_size' => 'File is too small',
        'accept_file_types' => 'Filetype not allowed',
        'max_number_of_files' => 'Maximum number of files exceeded',
        'max_width' => 'Image exceeds maximum width',
        'min_width' => 'Image requires a minimum width',
        'max_height' => 'Image exceeds maximum height',
        'min_height' => 'Image requires a minimum height'
    );

    function __init($options = null, $initialize = false)
    {
        $this->options = array(
            'script_url' => Router::url('/', true),
            'upload_dir' => dirname($_SERVER['SCRIPT_FILENAME']) . '/files/',
            'upload_url' => Router::url('/files', true) . '/',
            'user_dirs' => false,
            'mkdir_mode' => 0755,
            'param_name' => 'files',
            // Set the following option to 'POST', if your server does not support
            // DELETE requests. This is a parameter sent to the client:
            'delete_type' => 'DELETE',
            'access_control_allow_origin' => '*',
            // Enable to provide file downloads via GET requests to the PHP script:
            'download_via_php' => false,
            // Defines which files can be displayed inline when downloaded:
            'inline_file_types' => '/\.(gif|jpe?g|png)$/i',
            // Defines which files (based on their names) are accepted for upload:
            'accept_file_types' => '/.+$/i',
            // The php.ini settings upload_max_filesize and post_max_size
            // take precedence over the following max_file_size setting:
            'max_file_size' => null,
            'min_file_size' => 1,
            // The maximum number of files for the upload directory:
            'max_number_of_files' => null,
            // Image resolution restrictions:
            'max_width' => null,
            'max_height' => null,
            'min_width' => 1,
            'min_height' => 1,
            // Set the following option to false to enable resumable uploads:
            'discard_aborted_uploads' => true,
            // Set to true to rotate images based on EXIF meta data, if available:
            'orient_image' => false,
            'image_versions' => array(
                // Uncomment the following version to restrict the size of
                // uploaded images:
                /*
                '' => array(
                    'max_width' => 1920,
                    'max_height' => 1200,
                    'jpeg_quality' => 95
                ),
                */
                // Uncomment the following to create medium sized images:
                /*
                'medium' => array(
                    'max_width' => 800,
                    'max_height' => 600,
                    'jpeg_quality' => 80
                ),
                */
                'thumbnail' => array(
                    'max_width' => 80,
                    'max_height' => 80
                )
            )
        );
        if ($options) {
            $this->options = array_replace_recursive($this->options, $options);
        }
    }

    protected function getUserId()
    {
        @session_start();
        return session_id();
    }

    protected function getUserPath()
    {
        if ($this->options['user_dirs']) {
            return $this->getUserId() . '/';
        }
        return '';
    }

    protected function getUploadPath($file_name = null, $version = null)
    {
        $file_name = $file_name ? $file_name : '';
        $version_path = empty($version) ? '' : $version . '/';
        return $this->options['upload_dir'] . $this->getUserPath() . $version_path . $file_name;
    }

    protected function getDownloadUrl($file_name, $version = null)
    {
        if ($this->options['download_via_php']) {
            $url = $this->options['script_url'] . '?file=' . rawurlencode($file_name);
            if ($version) {
                $url .= '&version=' . rawurlencode($version);
            }
            return $url . '&download=1';
        }
        $version_path = empty($version) ? '' : rawurlencode($version) . '/';
        return $this->options['upload_url'] . $this->getUserPath()
        . $version_path . rawurlencode($file_name);
    }

    protected function setFileDeleteProperties($file)
    {
        $file->delete_url = Router::url('/attachments', true) . '?file=' . rawurlencode($file->name);
        $file->delete_type = $this->options['delete_type'];
        if ($file->delete_type !== 'DELETE') {
            $file->delete_url .= '&_method=DELETE';
        }
    }

    // Fix for overflowing signed 32 bit integers,
    // works for sizes up to 2^32-1 bytes (4 GiB - 1):
    protected function fixIntegerOverflow($size)
    {
        if ($size < 0) {
            $size += 2.0 * (PHP_INT_MAX + 1);
        }
        return $size;
    }

    protected function getFileSize($file_path, $clear_stat_cache = false)
    {
        if ($clear_stat_cache) {
            clearstatcache();
        }
        return $this->fixIntegerOverflow(filesize($file_path));

    }

    protected function isValidFileObject($file_name)
    {
        $file_path = $this->getUploadPath($file_name);
        if (is_file($file_path) && $file_name[0] !== '.') {
            return true;
        }
        return false;
    }

    protected function getFileObject($file_name)
    {
        if ($this->isValidFileObject($file_name)) {
            $file = new stdClass();
            $file->name = $file_name;
            $file->size = $this->getFileSize(
                $this->getUploadPath($file_name)
            );
            $file->url = $this->getDownloadUrl($file->name);
            foreach ($this->options['image_versions'] as $version => $options) {
                if (!empty($version)) {
                    if (is_file($this->getUploadPath($file_name, $version))) {
                        $file->{$version . '_url'} = $this->getDownloadUrl(
                            $file->name,
                            $version
                        );
                    }
                }
            }
            $this->setFileDeleteProperties($file);
            return $file;
        }
        return null;
    }

    protected function getFileObjects($iteration_method = 'get_file_object')
    {
        $upload_dir = $this->getUploadPath();
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, $this->options['mkdir_mode']);
        }
        return array_values(array_filter(array_map(
            array($this, $iteration_method),
            scandir($upload_dir)
        )));
    }

    protected function countFileObjects()
    {
        return count($this->getFileObjects('is_valid_file_object'));
    }

    protected function createScaledImage($file_name, $version, $options)
    {
        $file_path = $this->getUploadPath($file_name);
        if (!empty($version)) {
            $version_dir = $this->getUploadPath(null, $version);
            if (!is_dir($version_dir)) {
                mkdir($version_dir, $this->options['mkdir_mode']);
            }
            $new_file_path = $version_dir . '/' . $file_name;
        } else {
            $new_file_path = $file_path;
        }
        list($img_width, $img_height) = @getimagesize($file_path);
        if (!$img_width || !$img_height) {
            return false;
        }
        $scale = min(
            $options['max_width'] / $img_width,
            $options['max_height'] / $img_height
        );
        if ($scale >= 1) {
            if ($file_path !== $new_file_path) {
                return copy($file_path, $new_file_path);
            }
            return true;
        }
        $new_width = $img_width * $scale;
        $new_height = $img_height * $scale;
        $new_img = @imagecreatetruecolor($new_width, $new_height);
        switch (strtolower(substr(strrchr($file_name, '.'), 1))) {
            case 'jpg':
            case 'jpeg':
                $src_img = @imagecreatefromjpeg($file_path);
                $write_image = 'imagejpeg';
                $image_quality = isset($options['jpeg_quality']) ?
                    $options['jpeg_quality'] : 75;
                break;
            case 'gif':
                @imagecolortransparent($new_img, @imagecolorallocate($new_img, 0, 0, 0));
                $src_img = @imagecreatefromgif($file_path);
                $write_image = 'imagegif';
                $image_quality = null;
                break;
            case 'png':
                @imagecolortransparent($new_img, @imagecolorallocate($new_img, 0, 0, 0));
                @imagealphablending($new_img, false);
                @imagesavealpha($new_img, true);
                $src_img = @imagecreatefrompng($file_path);
                $write_image = 'imagepng';
                $image_quality = isset($options['png_quality']) ?
                    $options['png_quality'] : 9;
                break;
            default:
                $src_img = null;
        }
        $success = $src_img && @imagecopyresampled(
                $new_img,
                $src_img,
                0, 0, 0, 0,
                $new_width,
                $new_height,
                $img_width,
                $img_height
            ) && $write_image($new_img, $new_file_path, $image_quality);
        // Free up memory (imagedestroy does not delete files):
        @imagedestroy($src_img);
        @imagedestroy($new_img);
        return $success;
    }

    protected function getErrorMessage($error)
    {
        return array_key_exists($error, $this->error_messages) ?
            $this->error_messages[$error] : $error;
    }

    protected function isValidFile($uploaded_file, $file, $error, $index)
    {
        $args = func_get_args();
        $this->__log(__METHOD__, $args);
        if ($error) {
            $file->error = $this->getErrorMessage($error);
            return false;
        }
        if (!$file->name) {
            $file->error = $this->getErrorMessage('missingFileName');
            return false;
        }
        if (!preg_match($this->options['accept_file_types'], $file->name)) {
            $file->error = $this->getErrorMessage('accept_file_types');
            return false;
        }
        if ($uploaded_file && is_uploaded_file($uploaded_file)) {
            $file_size = $this->getFileSize($uploaded_file);
        } else {
            $file_size = $_SERVER['CONTENT_LENGTH'];
        }
        if ($this->options['max_file_size'] && (
                $file_size > $this->options['max_file_size'] ||
                $file->size > $this->options['max_file_size'])
        ) {
            $file->error = $this->getErrorMessage('max_file_size');
            return false;
        }
        if ($this->options['min_file_size'] &&
            $file_size < $this->options['min_file_size']
        ) {
            $file->error = $this->getErrorMessage('min_file_size');
            return false;
        }
        if (is_int($this->options['max_number_of_files']) && (
                $this->countFileObjects() >= $this->options['max_number_of_files'])
        ) {
            $file->error = $this->getErrorMessage('max_number_of_files');
            return false;
        }
        list($img_width, $img_height) = @getimagesize($uploaded_file);
        if (is_int($img_width)) {
            if ($this->options['max_width'] && $img_width > $this->options['max_width']) {
                $file->error = $this->getErrorMessage('max_width');
                return false;
            }
            if ($this->options['max_height'] && $img_height > $this->options['max_height']) {
                $file->error = $this->getErrorMessage('max_height');
                return false;
            }
            if ($this->options['min_width'] && $img_width < $this->options['min_width']) {
                $file->error = $this->getErrorMessage('min_width');
                return false;
            }
            if ($this->options['min_height'] && $img_height < $this->options['min_height']) {
                $file->error = $this->getErrorMessage('min_height');
                return false;
            }
        }
        return true;
    }

    protected function trimFileName($name, $type, $index, $content_range)
    {
        // Remove path information and dots around the filename, to prevent uploading
        // into different directories or replacing hidden system files.
        // Also remove control characters and spaces (\x00..\x20) around the filename:
        $file_name = trim(basename(stripslashes($name)), ".\x00..\x20");
        // Add missing file extension for known image types:
        if (strpos($file_name, '.') === false &&
            preg_match('/^image\/(gif|jpe?g|png)/', $type, $matches)
        ) {
            $file_name .= '.' . $matches[1];
        }

        return $file_name;
    }

    protected function handleFormData($file, $index)
    {
        // Handle form data, e.g. $_REQUEST['description'][$index]
    }

    protected function orient_image($file_path)
    {
        $exif = @exif_read_data($file_path);
        if ($exif === false) {
            return false;
        }
        $orientation = intval(@$exif['Orientation']);
        if (!in_array($orientation, array(3, 6, 8))) {
            return false;
        }
        $image = @imagecreatefromjpeg($file_path);
        switch ($orientation) {
            case 3:
                $image = @imagerotate($image, 180, 0);
                break;
            case 6:
                $image = @imagerotate($image, 270, 0);
                break;
            case 8:
                $image = @imagerotate($image, 90, 0);
                break;
            default:
                return false;
        }
        $success = imagejpeg($image, $file_path);
        // Free up memory (imagedestroy does not delete files):
        @imagedestroy($image);
        return $success;
    }

    protected function handleFileUpload($uploaded_file, $name, $size, $type, $error,
                                          $index = null, $content_range = null)
    {
        $args = func_get_args();
        $this->__log(__METHOD__, $args);
        $file = new stdClass();
        $file->name = $this->trimFileName($name, $type, $index, $content_range);
        $file->orig_name = $file->name;
        $file->name = $this->generateFileName($file->name);
        $file->size = $this->fixIntegerOverflow(intval($size));
        $file->type = $type;
        if ($this->isValidFile($uploaded_file, $file, $error, $index)) {
            $this->handleFormData($file, $index);
            $upload_dir = $this->getUploadPath();
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, $this->options['mkdir_mode']);
            }
            $file_path = $this->getUploadPath($file->name);
            $file->path = $file_path;
            $append_file = $content_range && is_file($file_path) &&
                $file->size > $this->getFileSize($file_path);
            if ($uploaded_file && is_uploaded_file($uploaded_file)) {
                // multipart/formdata uploads (POST method uploads)
                if ($append_file) {
                    file_put_contents(
                        $file_path,
                        fopen($uploaded_file, 'r'),
                        FILE_APPEND
                    );
                } else {
                    move_uploaded_file($uploaded_file, $file_path);
                }
            } else {
                // Non-multipart uploads (PUT method support)
                file_put_contents(
                    $file_path,
                    fopen('php://input', 'r'),
                    $append_file ? FILE_APPEND : 0
                );
            }
            $file_size = $this->getFileSize($file_path, $append_file);
            if ($file_size === $file->size) {
                if ($file_id = $this->Attachment->createFile($file)) {
                    $file->id = $file_id;
                    if ($this->options['orient_image']) {
                        $this->orient_image($file_path);
                    }
                    $file->url = $this->getDownloadUrl($file->name);
                    foreach ($this->options['image_versions'] as $version => $options) {
                        if ($this->createScaledImage($file->name, $version, $options)) {
                            if (!empty($version)) {
                                $file->{$version . '_url'} = $this->getDownloadUrl(
                                    $file->name,
                                    $version
                                );
                            } else {
                                $file_size = $this->getFileSize($file_path, true);
                            }
                        }
                    }
                } else {
                    // TODO: handle error
                }
            } else if (!$content_range && $this->options['discard_aborted_uploads']) {
                unlink($file_path);
                $file->error = 'abort';
            }
            $file->size = $file_size;
            $this->setFileDeleteProperties($file);
        }
        return $file;
    }

    protected function printResponse($content, $print_response = true)
    {
        if ($print_response) {
            $json = json_encode($content);
            $redirect = isset($_REQUEST['redirect']) ?
                stripslashes($_REQUEST['redirect']) : null;
            if ($redirect) {
                header('Location: ' . sprintf($redirect, rawurlencode($json)));
                exit;
                return;
            }
            $this->printHeader();
            if (isset($_SERVER['HTTP_CONTENT_RANGE']) && is_array($content) &&
                is_object($content[0]) && $content[0]->size
            ) {
                header('Range: 0-' . ($this->fixIntegerOverflow(intval($content[0]->size)) - 1));
            }
            echo $json;
        }
        return $content;
    }

    protected function getVersionParam()
    {
        return isset($_GET['version']) ? basename(stripslashes($_GET['version'])) : null;
    }

    protected function getFilenameParam()
    {
        return isset($_GET['file']) ? basename(stripslashes($_GET['file'])) : null;
    }

    protected function getFileType($file_path)
    {
        switch (strtolower(pathinfo($file_path, PATHINFO_EXTENSION))) {
            case 'jpeg':
            case 'jpg':
                return 'image/jpeg';
            case 'png':
                return 'image/png';
            case 'gif':
                return 'image/gif';
            default:
                return '';
        }
    }

    protected function download()
    {
        if (!$this->options['download_via_php']) {
            header('HTTP/1.1 403 Forbidden');
            return;
        }
        $file_name = $this->getFilenameParam();
        if ($this->isValidFileObject($file_name)) {
            $file_path = $this->getUploadPath($file_name, $this->getVersionParam());
            if (is_file($file_path)) {
                if (!preg_match($this->options['inline_file_types'], $file_name)) {
                    header('Content-Description: File Transfer');
                    header('Content-Type: application/octet-stream');
                    header('Content-Disposition: attachment; filename="' . $file_name . '"');
                    header('Content-Transfer-Encoding: binary');
                } else {
                    // Prevent Internet Explorer from MIME-sniffing the content-type:
                    header('X-Content-Type-Options: nosniff');
                    header('Content-Type: ' . $this->getFileType($file_path));
                    header('Content-Disposition: inline; filename="' . $file_name . '"');
                }
                header('Content-Length: ' . $this->getFileSize($file_path));
                header('Last-Modified: ' . gmdate('D, d M Y H:i:s T', filemtime($file_path)));
                readfile($file_path);
            }
        }
    }

    public function printHeader()
    {
        header('Pragma: no-cache');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Content-Disposition: inline; filename="files.json"');
        // Prevent Internet Explorer from MIME-sniffing the content-type:
        header('X-Content-Type-Options: nosniff');
        if ($this->options['access_control_allow_origin']) {
            header('Access-Control-Allow-Origin: ' . $this->options['access_control_allow_origin']);
            header('Access-Control-Allow-Methods: OPTIONS, HEAD, GET, POST, PUT, DELETE');
            header('Access-Control-Allow-Headers: '
                . 'Content-Type, Content-Range, Content-Disposition, Content-Description');
        }
        header('Vary: Accept');
        if (isset($_SERVER['HTTP_ACCEPT']) &&
            (strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
        ) {
            header('Content-type: application/json');
        } else {
            header('Content-type: text/plain');
        }
    }

    function beforeFilter()
    {
        $this->log($this->action, 'rest');
        $this->log('REQUEST_METHOD: ' . $_SERVER['REQUEST_METHOD'], 'rest');
        $this->log(print_r(array($this->request->params, $this->request->data, $this->request->query), true), 'rest');

        $this->__init();
    }

    function index()
    {
        $file_name = $this->getFilenameParam();
        if ($file_name) {
            $info = $this->getFileObject($file_name);
        } else {
            $info = $this->getFileObjects();
        }

        $this->printHeader();
        $this->viewClass = 'Json';
        $this->set('json', $info);
        $this->set('_serialize', 'json');
    }

    function add()
    {
        // $upload = isset($_FILES[$this->options['param_name']]) ?  $_FILES[$this->options['param_name']] : null;
        $upload = isset($this->request->form[$this->options['param_name']]) ? $this->request->form[$this->options['param_name']] : null;
        $this->__log($upload);
        $this->__log($this->options['param_name']);
        // Parse the Content-Disposition header, if available:
        $file_name = isset($_SERVER['HTTP_CONTENT_DISPOSITION']) ?
            rawurldecode(preg_replace(
                '/(^[^"]+")|("$)/',
                '',
                $_SERVER['HTTP_CONTENT_DISPOSITION']
            )) : null;
        $file_type = isset($_SERVER['HTTP_CONTENT_DESCRIPTION']) ? $_SERVER['HTTP_CONTENT_DESCRIPTION'] : null;

        // Parse the Content-Range header, which has the following form:
        // Content-Range: bytes 0-524287/2000000
        $content_range = isset($_SERVER['HTTP_CONTENT_RANGE']) ? split('[^0-9]+', $_SERVER['HTTP_CONTENT_RANGE']) : null;
        $size = $content_range ? $content_range[3] : null;
        $info = array();

        $_data = $this->request->data;
        if (isset($_data['redirect'])) unset($_data['redirect']);
        $this->__log($_data);

        if ($upload && is_array($upload['tmp_name'])) {
            // param_name is an array identifier like "files[]",
            // $_FILES is a multi-dimensional array:
            foreach ($upload['tmp_name'] as $index => $value) {
                $return = $this->handleFileUpload(
                    $upload['tmp_name'][$index],
                    $file_name ? $file_name : $upload['name'][$index],
                    $size ? $size : $upload['size'][$index],
                    $file_type ? $file_type : $upload['type'][$index],
                    $upload['error'][$index],
                    $index,
                    $content_range
                );

                $this->__log(__LINE__, $return);
                foreach ($_data as $key => $val) {
                    $return->{$key} = $val;
                }
                $info[] = $return;
            }
        } else {
            // param_name is a single object identifier like "file",
            // $_FILES is a one-dimensional array:
            $this->__log($file_type, $upload, $_SERVER);
            $return = $this->handleFileUpload(
                isset($upload['tmp_name']) ? $upload['tmp_name'] : null,
                $file_name ? $file_name : (isset($upload['name']) ? $upload['name'] : null),
                $size ? $size : (isset($upload['size']) ? $upload['size'] : $_SERVER['CONTENT_LENGTH']),
                $file_type ? $file_type : (isset($upload['type']) ? $upload['type'] : $_SERVER['CONTENT_TYPE']),
                isset($upload['error']) ? $upload['error'] : null,
                null,
                $content_range
            );

            $this->__log(__LINE__, $return);
            foreach ($_data as $key => $val) {
                $return->{$key} = $val;
            }
            $info[] = $return;
        }

        $redirect = isset($this->request->data['redirect']) ? stripslashes($this->request->data['redirect']) : null;
        if ($redirect) {
            $json = Set::reverse($info);
            $this->__log($json);
            foreach ($json as &$item) {
                unset($item['path']);
            }
            $this->__log($json);
            $json = json_encode($json);
            $this->redirect(sprintf($redirect, rawurlencode($json)));
            exit;
        } else {
            $this->printHeader();
            if (isset($_SERVER['HTTP_CONTENT_RANGE']) && is_array($content) && is_object($content[0]) && $content[0]->size) {
                header('Range: 0-' . ($this->fixIntegerOverflow(intval($content[0]->size)) - 1));
            }
            $this->viewClass = 'Json';
            $this->set('json', $info);
            $this->set('_serialize', 'json');
        }
    }

    function delete()
    {
        // TODO: get_file_name_param() method 삭제하기
        $file_name = $this->request->query['file'];
        $file_path = $this->getUploadPath($file_name);
        $success = is_file($file_path) && $file_name[0] !== '.' && unlink($file_path);
        if ($success) {
            foreach ($this->options['image_versions'] as $version => $options) {
                if (!empty($version)) {
                    $file = $this->getUploadPath($file_name, $version);
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
            }
        }
        $this->__log($success);
        $this->printHeader();
        $this->viewClass = 'Json';
        $this->set('json', $success);
        $this->set('_serialize', 'json');
    }

    function __log()
    {
        $args = func_get_args();
        if (count($args) == 1) {
            $args = $args[0];
        }
        $this->log(print_r($args, true), 'rest');
    }

    protected function generateFileName($filename)
    {
        $ext = strtolower(end(preg_split('/\./', $filename)));
        $return = date('YmdHis') . '_' . md5($filename);
        if (!empty($ext)) {
            $return .= ".$ext";
        }

        return $return;
    }

    function options()
    {
        $this->printHeader();
        exit;
    }

}
