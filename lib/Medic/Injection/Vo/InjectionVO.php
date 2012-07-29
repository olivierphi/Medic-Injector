<?php

/*
 * This file is part of the Medic-Injector package.
 *
 * (c) Olivier Philippon <https://github.com/DrBenton>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Medic\Injection\Vo;

class InjectionVO
{
    /**
     * @var string
     */
    public $targetName;

    /**
     * @var string
     */
    public $injectionVarType;

    /**
     * @var string
     */
    public $injectionName;

    /**
     * If this is set to 'true', when the Injector will try to perform the injection it will throw an error
     * if no class mapping is found for this injection.
     *
     * @var boolean
     */
    public $mandatoryInjection;
}