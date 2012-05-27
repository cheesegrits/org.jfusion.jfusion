<?php

/**
 * Exented mysqli model that supports rollbacks
 * 
 * PHP version 5
 * 
 * @category  JFusion
 * @package   Models
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * Make sure the database model is loaded
 * Note: We cannot use jimport here as it does not include the file unless needed (if using php5+)
 * This leads to a fatal error if the plugin uses a different driver than Joomla
 */
require_once JPATH_LIBRARIES . DS . 'joomla' . DS . 'database' . DS . 'database' . DS . 'mysqli.php';

/**
 * Extention of the mysqli database object to support rollbacks
 * 
 * @category  JFusion
 * @package   Models
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
class JFusionMySQLi extends JDatabaseMySQLi
{

    /**
     * @param array $options
     */
    public function __construct($options){
        parent::__construct($options);
    }

    /**
     * begin transaction function
     * 
     */
    function BeginTrans()
    {
        mysqli_autocommit($this->_resource, false);
    }
    /**
     * commit transaction
     * 
     */
    function CommitTrans()
    {
        mysqli_commit($this->_resource);
    }
    /**
     * rollback trasnaction
     * 
     */
    function RollbackTrans()
    {
        mysqli_rollback($this->_resource);
    }
    
    /**
     * added execute query as Joomla 1.6 has removed this function
     *
     * @param string $query
     *
     */
    function Execute($query)
    {
    	$this->setQuery($query);
        $this->query();
    }    
}
