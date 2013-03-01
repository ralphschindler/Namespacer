<?php

namespace Namespacer\Model;

class Map
{
    protected $mapData;

    public function __construct($mapData = array())
    {
        $this->mapData = $mapData;
    }

    public function addFileData(array $data)
    {
        $this->mapData[] = $data;
    }

    public function getData()
    {
        return var_export($this->mapData, true);
    }

    public function getFileRenamings()
    {
        $data = array();
        foreach ($this->mapData as $item) {
            $o = $item['original_file'];
            $n = $item['new_file'];
            $data[$o] = $n;
        }
        return $data;
    }

    public function getNameModifications()
    {
        $data = array();
        foreach ($this->mapData as $item) {
            $data[$item['new_file']] = array(
                'namespace' => $item['new_namespace'],
                'class' => $item['new_class']
            );
        }
        return $data;
    }

    public function getNewFiles()
    {
        $data = array();
        foreach ($this->mapData as $item) {
            $data[] = $item['new_file'];
        }
        return $data;
    }

    public function getClassTransformations()
    {
        $data = array();
        foreach ($this->mapData as $item) {
            $data[$item['original_class']] = $item['new_namespace'] . '\\' . $item['new_class'];
        }
        return $data;
    }
}