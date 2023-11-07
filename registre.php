<?php

/**
 * jemdev\dbrm
 *
 * Fork de la classe Hoa\Registry très notablement simplifiée.
 *
 * @license New BSD License
 *
 * Copyright © 2007-2017, Hoa community. All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *     * Redistributions of source code must retain the above copyright
 *       notice, this list of conditions and the following disclaimer.
 *     * Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *     * Neither the name of the Hoa nor the names of its contributors may be
 *       used to endorse or promote products derived from this software without
 *       specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDERS AND CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

namespace jemdev\dbrm;

/**
 * Class \jemdev\dbrm\registre.
 *
 * Conserve un enregistrement de quelque chose.
 *
 * @copyright  Copyright © 2007-2017 Hoa community
 * @license    New BSD License
 */
class registre extends \ArrayObject
{
    /**
     * Instance.
     *
     * @var \jemdev\dbrm\registre
     */
    private static $_instance = null;

    /**
     * Private constructor.
     *
     * @throws  \Hoa\Registry\Exception
     */
    public function __construct()
    {
        throw new jemdevDbrmException(
            'Vous ne pouvez pas instancierla classe '. __CLASS__ .'. Utilisez une des méthodes statiques set, get, remove ' .
            'et isRegistered à la place.',
            E_USER_ERROR
        );
        return;
    }

    /**
     * Récupération d'une instance de \jemdev\dbrm\registre.
     *
     * @return  object
     */
    protected static function getInstance(): \ArrayObject
    {
        if (null === static::$_instance) {
            static::$_instance = new parent();
        }
        return static::$_instance;
    }

    /**
     * Créer un nouvel enregistrement
     *
     * @param   mixed   $index     Index du registre.
     * @param   mixed   $value     Valeur du registre.
     * @return  void
     */
    public static function set($index, $value): void
    {
        static::getInstance()->offsetSet($index, $value);
        return;
    }

    /**
     * Récupérer un enregistrement.
     *
     * @param   mixed   $index     Index du registre.
     * @return  mixed
     * @throws  jemdevDbrmException
     */
    public static function get($index): mixed
    {
        $registry = static::getInstance();

        if (false === $registry->offsetExists($index)) {
            throw new jemdevDbrmException('Registry '. $index .' does not exist.', E_USER_WARNING);
        }
        return $registry->offsetGet($index);
    }

    /**
     * Vérifie l'existence d'un index dans le registre.
     *
     * @param   mixed   $index     Index du registre.
     * @return  bool
     */
    public static function isRegistered($index): bool
    {
        return static::getInstance()->offsetExists($index);
    }

    /**
     * Supprime un enregistrement.
     *
     * @param   mixed   $index    Index du registre.
     * @return  void
     */
    public static function remove($index): void
    {
        static::getInstance()->offsetUnset($index);
        return;
    }
}
