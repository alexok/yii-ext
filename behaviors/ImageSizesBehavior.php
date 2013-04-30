<?php

/**
 * @property string $tmbSrc
 * @property string $src
 *
 * @property CActiveRecord $owner
 */
class ImageSizesBehavior extends CActiveRecordBehavior
{
    public $sizes;
    public $path;

    private $_path;
    private $_dir;

    private function getPath()
    {
        if ($this->_path === null) {
            /* @var WorkImage $owner */
            $owner = $this->getOwner();

            if (!method_exists($owner, 'getPath')) {
                throw new CException('Can`t get path for image storage');
            }

            return $this->_path = $owner->getPath();
        }

        return $this->_path;
    }

    private function getDir()
    {
        if ($this->_dir === null) {
            /* @var WorkImage $owner */
            $owner = $this->getOwner();

            if (!method_exists($owner, 'getPathAlias')) {
                throw new CException('Can`t get path alias for image storage');
            }

            return $this->_dir = str_replace(array('webroot', '.'), array('', '/'), $owner->getPathAlias());
        }

        return $this->_dir;
    }

    public function hasSize($name)
    {
        return isset($this->sizes[$name]);
    }

    public function getSizeTypes()
    {
        return array_keys($this->sizes);
    }

    public function getSizeInfo($name)
    {
        if (!$this->hasSize($name))
            throw new CException("Size `$name` not defined");

        return (object) $this->sizes[$name];
    }

    /**
     * Get link to image of size
     * @param string $name
     * @param boolean $defaultValue
     * @return bool|string
     */
    public function getSrc($name = 'normal', $defaultValue = false)
    {
        if ($src = $this->resizedImage($name)) {
            return $src;
        }

        return $defaultValue;
    }

    /**
     * Get thumbnail size
     * @return bool|string
     */
    public function getTmbSrc()
    {
        return $this->getSrc('tmb');
    }

    /**
     * Get original size
     * @param $defaultValue
     * @return string|boolean
     */
    public function getOrigSrc($defaultValue = false)
    {
        $path = $this->getPath();
        $filename = $this->getOwner()->filename;

        if (is_file($path .DS. $filename)) {
            return sprintf('/images/%s/%s', $this->dir, $filename);
        }

        return $defaultValue;
    }

    public function getAllLinks()
    {
        $array = array();

        foreach(array_keys($this->sizes) as $name) {
            $array[$name] = $this->getSrc($name);
        }

        return $array;
    }

    /**
     * @param $type
     * @return string|boolean
     */
    private function resizedImage($type)
    {
        $filename = $this->sizeFileName($type);

        $path = $this->getPath();
        $orig_filename = $this->getOwner()->filename;

        $url = $this->getDir() .'/'. $filename;

        if (is_file($path .DS. $filename)) {
            return $url .'?'. filemtime($path .DS. $filename);
        }

        if (is_file($path .DS. $orig_filename)
            && $this->createImage($orig_filename, $type, $filename)) {
            return $url .'?'. filemtime($path .DS. $filename);
        }

        return false;
    }

    public function sizeFileName($type, $name = null)
    {
        if ($name === null) {
            $name = $this->getOwner()->filename;
        }

        if (isset($this->sizes[$type])) {
            $suffix = $this->sizes[$type]['suffix'];

            $ext = CFileHelper::getExtension($name);
            $name = str_replace('.'.$ext, $suffix.'.'.$ext, $name);
        } else {
            throw new CException("Size `$type` not defined");
        }

        return $name;
    }

    private function checkImages()
    {
        foreach(array_keys($this->sizes) as $size) {
            $this->resizedImage($size);
        }
    }

    public function afterSave($event)
    {
        $this->checkImages();

        parent::afterSave($event);
        return true;
    }

    public function afterFind($event)
    {
        $this->checkImages();

        parent::afterFind($event);
        return true;
    }

    public function afterDelete($event)
    {
        $path    = $this->getPath();
        $files[] = $this->getOwner()->filename;

        foreach(array_keys($this->sizes) as $size) {
            $files[] = $this->sizeFileName($size);
        }

        foreach($files as $f) {
            if (is_file($path .DS. $f))
                unlink($path .DS. $f);
        }

        parent::afterDelete($event);
        return true;
    }


    /**
     * @param $from
     * @param $type
     * @param null $to
     * @return string|boolean
     */
    private function createImage($from, $type, $to = null)
    {
        if ($to == null) {
            $to = $this->sizeFileName($type, $from);
        }

        $params = $this->sizes[$type];

        /* @var Image $image */
        $image = Yii::app()->getComponent('image')->load($this->getPath() .DS. $from);

        if (isset($params['masterSize'])) {
            $masterSize = $params['masterSize'];
        } else {
            if (isset($params['crop']))
                $masterSize = $image->width > $image->height ? Image::HEIGHT : Image::WIDTH;
            else
                $masterSize = $image->width > $image->height ? Image::WIDTH : Image::HEIGHT;
        }

        if ($image->width > $params['size']) {
            $image->resize($params['size'], $params['size'], $masterSize);

            if (isset($params['crop']) && $params['crop'] == 1) {
                $image->crop($params['size'], $params['size'], $params['crop']);
            }
        }

        return $image->save($this->getPath() .DS. $to, false);
    }
}