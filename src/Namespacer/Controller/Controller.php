<?php

namespace Namespacer\Controller;

use Namespacer\Model\Map;
use Namespacer\Model\Mapper;
use Namespacer\Model\Transformer;
use Zend\Mvc\Controller\AbstractActionController;

class Controller extends AbstractActionController
{
    public function createMapAction()
    {
        $mapfile = $this->params()->fromRoute('mapfile');
        $source = $this->params()->fromRoute('source');

        $map = array();

        $mapper = new Mapper();
        $mapdata = $mapper->getMapDataForDirectory($source);

        file_put_contents($mapfile, '<?php return ' . var_export($mapdata, true) . ';');

    }

    public function transformAction()
    {
        $mapfile = $this->params()->fromRoute('mapfile');
        $step = $this->params()->fromRoute('step');
        $data = include $mapfile;

        $map = new Map($data);
        $transformer = new Transformer($map);

        switch ($step) {
            case '3':
                $transformer->modifyContentForUseStatements();
                break;
            case '2':
                $transformer->modifyNamespaceAndClassNames();
                break;
            case '1':
                $transformer->moveFiles();
                break;
            default:
                $transformer->moveFiles();
                $transformer->modifyNamespaceAndClassNames();
                break;
        }


    }
}