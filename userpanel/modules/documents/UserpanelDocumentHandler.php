<?php

/**
 * LMS version 1.11-git
 *
 *  (C) Copyright 2001-2020 LMS Developers
 *
 *  Please, see the doc/AUTHORS for more information about authors!
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License Version 2 as
 *  published by the Free Software Foundation.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program; if not, write to the Free Software
 *  Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307,
 *  USA.
 *
 *  $Id$
 */

class UserpanelDocumentHandler
{
    private $db;
    private $smarty;
    private $customerid;
    private static $instance = null;

    public function __construct($db, $smarty, $customerid)
    {
        $this->db = $db;
        $this->smarty = $smarty;
        $this->customerid = $customerid;
        self::$instance = $this;
    }

    public static function getInstance()
    {
        return self::$instance;
    }

    public function getDocumentWarnings()
    {
        return $this->db->GetOne(
            'SELECT COUNT(*)
            FROM documents
            WHERE type < 0 AND customerid = ? AND (closed = 0 OR confirmdate = -1 OR confirmdate >= ?NOW?)',
            array($this->customerid)
        );
    }
}