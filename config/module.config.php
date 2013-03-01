<?php
return array(
    'Namespacer' => array(
        'disableUsage' => false,    // set to true to disable showing available ZFTool commands in Console.
    ),

    'controllers' => array(
        'invokables' => array(
            'Namespacer\Controller\Info' => 'Namespacer\Controller\InfoController',
            'Namespacer\Controller\Controller' => 'Namespacer\Controller\Controller',
        ),
    ),

    'console' => array(
        'router' => array(
            'routes' => array(
                'namespacer-version' => array(
                    'options' => array(
                        'route'    => 'version',
                        'defaults' => array(
                            'controller' => 'Namespacer\Controller\Info',
                            'action'     => 'version',
                        ),
                    ),
                ),
                'namespacer-version2' => array(
                    'options' => array(
                        'route'    => '--version',
                        'defaults' => array(
                            'controller' => 'Namespacer\Controller\Info',
                            'action'     => 'version',
                        ),
                    ),
                ),
                'namespacer-create-map' => array(
                    'options' => array(
                        'route'    => 'map [--mapfile=] [--source=]',
                        'defaults' => array(
                            'controller' => 'Namespacer\Controller\Controller',
                            'action'     => 'createMap',
                        ),
                    ),
                ),
                'namespacer-transform' => array(
                    'options' => array(
                        'route'    => 'transform [--mapfile=]',
                        'defaults' => array(
                            'controller' => 'Namespacer\Controller\Controller',
                            'action'     => 'transform',
                        ),
                    ),
                ),
            ),
        ),
    ),

);
