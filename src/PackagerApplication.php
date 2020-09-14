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
     */
    public function __construct(string $name = 'UNKNOWN')
    {
        $version = $this->version();
        parent::__construct($name, $version);
        $this->add(new PackagerCommand());
    }

    protected function version()
    {
        $data = json_decode(file_get_contents(__DIR__.'/../composer.json'), true);
        return $data['version'];
    }
}