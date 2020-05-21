<?php
/**
 * Author:  CodeSinging (The code is singing)
 * Email:   codesinging@gmail.com
 * Github:  https://github.com/codesinging
 * Time:    2020-05-21 09:27:31
 */

namespace CodeSinging\Packager;

use Symfony\Component\Console\Application;

class PackagerApplication extends Application
{
    /**
     * PackagerApplication constructor.
     * @param string $name
     * @param string $version
     */
    public function __construct(string $name = 'UNKNOWN', string $version = 'UNKNOWN')
    {
        parent::__construct($name, $version);
        $this->add(new PackagerCommand());
    }
}