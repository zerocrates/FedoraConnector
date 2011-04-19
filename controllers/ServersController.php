<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4; */

/**
 * FedoraConnector Omeka plugin allows users to reuse content managed in
 * institutional repositories in their Omeka repositories.
 *
 * The FedoraConnector plugin provides methods to generate calls against Fedora-
 * based content disemminators. Unlike traditional ingestion techniques, this
 * plugin provides a facade to Fedora-Commons repositories and records pointers
 * to the "real" objects rather than creating new physical copies. This will
 * help ensure longer-term durability of the content streams, as well as allow
 * you to pull from multiple institutions with open Fedora-Commons
 * respositories.
 *
 * PHP version 5
 *
 * Licensed under the Apache License, Version 2.0 (the "License"); you may not
 * use this file except in compliance with the License. You may obtain a copy of
 * the License at http://www.apache.org/licenses/LICENSE-2.0 Unless required by
 * applicable law or agreed to in writing, software distributed under the
 * License is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS
 * OF ANY KIND, either express or implied. See the License for the specific
 * language governing permissions and limitations under the License.
 *
 * @package     omeka
 * @subpackage  fedoraconnector
 * @author      Scholars' Lab <>
 * @author      Ethan Gruber <ewg4x@virginia.edu>
 * @author      Adam Soroka <ajs6f@virginia.edu>
 * @author      Wayne Graham <wayne.graham@virginia.edu>
 * @author      Eric Rochester <err8n@virginia.edu>
 * @copyright   2010 The Board and Visitors of the University of Virginia
 * @license     http://www.apache.org/licenses/LICENSE-2.0.html Apache 2 License
 * @version     $Id$
 * @link        http://omeka.org/add-ons/plugins/FedoraConnector/
 * @tutorial    tutorials/omeka/FedoraConnector.pkg
 */

require "Zend/Form/Element.php";
include_once '../form_utils.php';
include_once '../form_db.php';

/**
 * This class defines actions for the servers.
 */
class FedoraConnector_ServersController extends Omeka_Controller_Action
{

    /**
     * This controls creating the main page for the server item.
     *
     * @return void
     */
    public function indexAction()
    {
		$db = get_db();

    	$currentPage = $this->_getParam('page', 1);


        $count = $db
            ->getTable('FedoraConnector_Server')
            ->count();
        $this->view->servers = Fedora_Db_getDataForPage(
            'FedoraConnector_Server',
            $currentPage
        );
    	$this->view->count = $count;

        // Now process the pagination
        $baseUrl = $this->getRequest()->getBaseUrl();
        $paginationUrl = "$baseUrl/servers/index/";

        //Serve up the pagination
        $pagination = array(
            'page'          => $currentPage,
            'per_page'      => 10,
            'total_results' => $count,
            'link'          => $paginationUrl
        );

        Zend_Registry::set('pagination', $pagination);
    }

    /**
     * This handles the action for creating a server.
     *
     * @return void
     */
    public function createAction()
    {
    	$server = array();
    	$form = $this->_createServerForm($server);
		$this->view->form = $form;
    }

    /**
     * This handles editing a server.
     *
     * @return void
     */
    public function editAction()
    {
		$db = get_db();

        $count = $db
            ->getTable('FedoraConnector_Server')
            ->count();

        $server = $this->_getFormServer($db);
		$form = $this->_createServerForm($server);

		$this->view->count = $count;
		$this->view->id = $server->id;
		$this->view->form = $form;
    }

    /**
     * This handles deleting a server.
     *
     * @return void
     */
    public function deleteAction()
    {
        if ($user = $this->getCurrentUser()) {
			$db = get_db();

            $server = $this->_getFormServer($db);

			$server->delete();

			$this->flashSuccess('The server was successfully deleted!');
			$this->redirect->goto('index');

        } else {
            $this->_forward('forbidden');
        }
    }

    /**
     * This retrieves a server, taking it's ID from a form parameter.
     *
     * @param Omeka_Db $db  The database to get the server from. This defaults 
     * to null.
     * @param string   $key The form parameter to get the server ID from. This 
     * defaults to 'id'.
     *
     * @return Omeka_Record The record for the server or null.
     */
    private function _getFormServer($db=null, $key='id') {
        // XXX -> libraries/FedoraConnector/Viewer/Server.php
        if ($db === null) {
            $db = get_db();
        }

        $id = $this->_getParam($key);
        if ($id === null) {
            return null;
        }

        $server = $db
            ->getTable('FedoraConnector_Server')
            ->find($id);

        return $server;
    }

    /**
     * This handles updating the form.
     *
     * @return void
     */
    public function updateAction()
    {
        $form = $this->_createServerForm($server);

        if ($_POST) {
            if ($form->isValid($this->_request->getPost())) {
                $this->_updateServer($form->getValues());
            } else {
                $this->flashError('URL and server name are required.');
                $this->view->form = $form;
            }
        } else {
            $this->flashError('Failed to gather posted data.');
            $this->view->form = $form;
        }
    }

    /**
     * This takes the uploaded form data and either creates or updates a 
     * server.
     *
     * @param Zend_Form $form The form to pull data from.
     *
     * @return Omeka_Record The new/updated server instance.
     */
    private function _updateServer($form) {
        // XXX some -> models/FedoraConnector/Server.php
        $data = $form->getValues();

        $version = $this->_getServerVersion($data['url']);
        if ($version !== null) {
            $data = array(
                'name'       => $data['name'],
                'url'        => $data['url'],
                'is_default' => $data['is_default'],
                'version'    => $version
            );
            if ($data['method'] == 'update') {
                $data['id'] = $data['id'];
            }

            try {
                $db = get_db();

                // If the new server is the default, clear is_default
                // for the existing ones.
                if ($data['is_default'] == '1') {
                    $this->_resetIsDefault();
                }

                $db->insert('fedora_connector_servers', $data);

                $this->flashSuccess('Server updated.');
                $this->redirect->goto('index');

            } catch (Exception $e) {
                $this->flashError($e->getMessage());
            }

        } else {
            $this->flashError(
                'Server URL cannot be validated.  '
                . 'Not receiving Fedora repositoryVersion response.'
            );
            $this->view->form = $form;
        }
    }

    /**
     * This queries the Fedora Commons URL and returns the repository version.
     *
     * @param string $url The server's base URL.
     *
     * @return string The repository's server string, or null if none is found.
     */
    private function _getServerVersion($url) {
        // XXX -> models/FedoraConnector/Server.php
        $nodes = getQueryNodes(
            "{$url}describe?xml=true",
            "//*[local-name() = 'repositoryVersion']"
        );

        $version = null;
        foreach ($nodes as $node) {
            $version = $node->nodeValue;
        }

        return $version;
    }

    /**
     * This resets the is_default values for all servers.
     *
     * @param Omeka_Db $db The database.
     *
     * @return void
     */
    private function _resetIsDefault($db) {
        // XXX -> models/FedoraConnector/ServerTable.php
        $db
            ->getTable('FedoraConnector_Server')
            ->update(
                array('is_default' => '0'),
                "is_default = '1'"
            );
    }

    /**
     * This creates a server form.
     *
     * @param Omeka_Record $server The server to create the form for.
     *
     * @return Zend_Form The form for the server.
     */
    private function _createServerForm($server) {
        // XXX -> libraries/FedoraConnector/Viewer/Server.php
        $hasServer = ($server !== null);

        $form = Fedora_initForm('update', 'post', 'multipart/form-data');

        Fedora_Form_addText(
            $form,
            'url',
            'URL:',
            ($hasServer) ? $server->url : null,
            true
        );
        Fedora_Form_addText(
            $form,
            'name',
            'Name:',
            ($hasServer) ? $server->name : null,
            true
        );
        Fedora_Form_addCheckbox(
            $form,
            'is_default',
            'Is Default Server:',
            ($hasServer) ? $server->is_default : null
        );
        Fedora_Form_addHidden(
            $form,
            'id',
            ($hasServer) ? $server->id : null
        );
        Fedora_Form_addHidden(
            $form,
            'method',
            ($hasServer) ? 'update' : 'create'
        );

        Fedora_Form_addSubmit($form);

	    return $form;
    }
}

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * c-hanging-comment-ender-p: nil
 * End:
 */

?>