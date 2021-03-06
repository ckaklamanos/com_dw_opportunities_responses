<?php

/**
 * @version     1.0.0
 * @package     com_volunteers_responses
 * @copyright   Copyright (C) 2014. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      Charalampos Kaklamanos <dev.yesinternet@gmail.com> - http://www.yesinternet.gr
 */
defined('_JEXEC') or die;

jimport('joomla.application.component.modellist');
/**
 * Methods supporting a list of Volunteers_responses records.
 */
class Dw_opportunities_responsesModelDwOpportunitiesresponses extends JModelList
{

    /**
     * Constructor.
     *
     * @param    array    An optional associative array of configuration settings.
     * @see        JController
     * @since    1.6
     */
    public function __construct($config = array())
    {
        if (empty($config['filter_fields']))
        {
            $config['filter_fields'] = array(
				'id', 'a.id',
                'ordering', 'a.ordering',
                'state', 'a.state',
                'created_by', 'a.created_by',
                'created', 'a.created',
                'status', 'a.status',
                'opportunity_id', 'a.opportunity_id',
                'message', 'a.message',
                'parameters', 'a.parameters',
                'modified', 'a.modified',

            );
        }
        parent::__construct($config);
    }

    /**
     * Method to auto-populate the model state.
     *
     * Note. Calling getState in this method will result in recursion.
     *
     * @since    1.6
     */
	protected function populateState($ordering = null, $direction = null)
	{

		// Initialise variables.
		$app = JFactory::getApplication();

		// List state information
		$limit = $app->getUserStateFromRequest('global.list.limit', 'limit', $app->getCfg('list_limit'));
		$this->setState('list.limit', $limit);

		$limitstart = $app->input->getInt('limitstart', 0);
		$this->setState('list.start', $limitstart);

		if ($list = $app->getUserStateFromRequest($this->context . '.list', 'list', array(), 'array'))
		{   
			foreach ($list as $name => $value)
			{
				// Extra validations
				switch ($name)
				{
					case 'fullordering':
						$orderingParts = explode(' ', $value);

						if (count($orderingParts) >= 2)
						{
							// Latest part will be considered the direction
							$fullDirection = end($orderingParts);

							if (in_array(strtoupper($fullDirection), array('ASC', 'DESC', '')))
							{
								$this->setState('list.direction', $fullDirection);
							}

							unset($orderingParts[count($orderingParts) - 1]);

							// The rest will be the ordering
							$fullOrdering = implode(' ', $orderingParts);

							if (in_array($fullOrdering, $this->filter_fields))
							{
								$this->setState('list.ordering', $fullOrdering);
							}
						}
						else
						{
							$this->setState('list.ordering', $ordering);
							$this->setState('list.direction', $direction);
						}
						break;

					case 'ordering':
						if (!in_array($value, $this->filter_fields))
						{
							$value = $ordering;
						}
						break;

					case 'direction':
						if (!in_array(strtoupper($value), array('ASC', 'DESC', '')))
						{
							$value = $direction;
						}
						break;

					case 'limit':
						$limit = $value;
						break;

					// Just to keep the default case
					default:
						$value = $value;
						break;
				}

				$this->setState('list.' . $name, $value);
			}
		}

		// Receive & set filters
		if ($filters = $app->getUserStateFromRequest($this->context . '.filter', 'filter', array(), 'array'))
		{
			foreach ($filters as $name => $value)
			{
				$this->setState('filter.' . $name, $value);
			}
		}

		$ordering = $app->input->get('filter_order');
		if (!empty($ordering))
		{
			$list             = $app->getUserState($this->context . '.list');
			$list['ordering'] = $app->input->get('filter_order');
			$app->setUserState($this->context . '.list', $list);
		}

		$orderingDirection = $app->input->get('filter_order_Dir'); 
		if (!empty($orderingDirection))
		{
			$list              = $app->getUserState($this->context . '.list');
			$list['direction'] = $app->input->get('filter_order_Dir');
			$app->setUserState($this->context . '.list', $list);
		}
		
		//TO DO : Check why this code is useful. When uncommented, my fullordering list filter is reset, becasue the state is reset, so not working well.
		//$list = $app->getUserState($this->context . '.list'); var_dump($list);

		//if (empty($list['ordering']))
		//{
			//$list['ordering'] = 'ordering';
		//}

		//if (empty($list['direction']))
		//{
			//$list['direction'] = 'asc';
		//}

		//$this->setState('list.ordering', $list['ordering']);
		//$this->setState('list.direction', $list['direction']);
	}

    /**
     * Build an SQL query to load the list data.
     *
     * @return    JDatabaseQuery
     * @since    1.6
     */
    protected function getListQuery()
    {
        // Create a new query object.
        $db = $this->getDbo();
        $query = $db->getQuery(true);

        // Select the required fields from the table.
        $query -> select(
			$this->getState(
				'list.select', 'DISTINCT a.*'
                )
        );
	
        $query->from('`#__dw_opportunities_responses` AS a');

		//Component creator
		//Join over the users for the checked out user.
		//$query->select('uc.name AS editor');
		//$query->join('LEFT', '#__users AS uc ON uc.id=a.checked_out');
    
		// Join over the created by field 'created_by'
		$query->select('created_by.name AS created_by_name');
		$query->select('created_by.email AS created_by_email');

		$query->join('INNER', '#__users AS created_by ON created_by.id = a.created_by');

		// Join over the opportunity_id
		$query->select('opportunities.created_by AS opportunity_created_by');
		$query->select('opportunities.title AS opportunity_title');
		$query->select('opportunities.created AS opportunity_created');
		$query->join('LEFT', '#__dw_opportunities AS opportunities ON opportunities.id = a.opportunity_id');
		
		// Join over the response status
		$query->select('status.id AS status_id');
		$query->select('status.status AS status');
		//$query->select('statuses.state AS status_state');
		//$query->select('statuses.created_by AS status_created_by');
		$query->join('LEFT', '#__dw_opportunities_responses_statuses AS status ON status.response_id=a.id');

		$query->where('a.state = "1" ');

        // Filter by search in message,name etc
        $search = $this->getState('filter.search');
        
		if (!empty($search))
        {
            if (stripos($search, 'id:') === 0)
            {
                $query->where('a.id = ' . (int) substr($search, 3));
            }
            else
            {
                $search = $db->Quote('%' . $db->escape($search, true) . '%');
                
				
				$_where = array();
				$_where[] = 'a.message LIKE ' . $search;
				
				
				
				
				//$_where[] = 'status LIKE ' . $search;
				
				//if ($filter_opportunity_created_by) {
					//$_where[] = 'opportunities.title LIKE ' . $search;
				//}

				$where = '(' . implode(') OR (', $_where) . ')';
				$query->where($where);
				
            }
        }
		
      
		//Filtering status
		$filter_status = $this->state->get("filter.status");
		
		if ($filter_status) 
		{
			//$query->where("status = '".$db->escape($filter_status)."' or status is null");
			 if( $filter_status == 'pending' )
				 //$query->where(" (status = '".$db->escape($filter_status)."') OR (status = NULL)");
				 $query->where("(status IS NULL OR status='".$db->escape($filter_status)."')");
			  else
				  $query->where("status = '".$db->escape($filter_status)."'");
		}
		
		//Checking "_dateformat"
		$filter_created_from = $this->state->get("filter.created_from_dateformat");
		if ($filter_created_from && preg_match("/^(19|20)\d\d[-](0[1-9]|1[012])[-](0[1-9]|[12][0-9]|3[01])$/", $filter_created_from) && date_create($filter_created_from) ) {
			$query->where("a.created >= '".$db->escape($filter_created_from)."'");
		}
		$filter_created_to = $this->state->get("filter.created_to_dateformat");
		if ($filter_created_to && preg_match("/^(19|20)\d\d[-](0[1-9]|1[012])[-](0[1-9]|[12][0-9]|3[01])$/", $filter_created_to) && date_create($filter_created_to) ) {
			$query->where("a.created <= '".$db->escape($filter_created_to)."'");
		}

		//Filtering opportunity_id
		$filter_opportunity_id = $this->state->get("filter.opportunity_id");
		if ($filter_opportunity_id) {
			$query->where("a.opportunity_id = '".$db->escape($filter_opportunity_id)."'");
		}
		
		//Filtering opportunity_created_by
		$filter_opportunity_created_by = $this->state->get("filter.opportunity_created_by");
		if ($filter_opportunity_created_by) {		
			$query->where('opportunities.created_by ="'.$filter_opportunity_created_by.'"');
		}
		
		//Filtering created_by
		$filter_created_by = $this->state->get("filter.created_by");
		if ($filter_created_by) {
			$query->where("a.created_by = '".$db->escape($filter_created_by)."'");
		}

		// Add the list ordering clause.
		$orderCol  = $this->state->get('list.ordering');
		$orderDirn = $this->state->get('list.direction');
		
		if ($orderCol && $orderDirn)
		{
			$query->order($db->escape($orderCol . ' ' . $orderDirn));
		}


        return $query;
    }

    public function getItems()
    {
        $items = parent::getItems();
        
		foreach($items as $item){
			
			
			$registry = new JRegistry;
			$registry->loadString($item->parameters);
			$item->parameters = $registry->toArray();
			
			if ( !$item -> status )
				$item -> status = 'pending';
			
			$item -> created_telephone = ( isset ( $item->parameters ['telephone'] ) ) ? $item->parameters ['telephone'] : '' ;
		}
        
		return $items;
    }
	
    public function getItemsByVolunteer( $volunteer_id , $opportunity_id )
	{
		$this -> setState ('filter.created_by' , $volunteer_id );
		$this -> setState ('filter.opportunity_id' , $opportunity_id );

		return $this->getItems();
	}

    public function getItemsByUser( $user , $opportunity_id = null )
    {
	
		if( !$user ){
			return null;
		}
		
		if( $user -> guest ){
			return null;
		}
		
		$canCreateResponse = $user->authorise('core.create', 'com_dw_opportunities_responses');
		
		if(!$canCreateResponse){
			return null;
		}
		
		
		$this -> setState('list.ordering', 'created');
        $this -> setState('list.direction', 'desc');
		
		$this -> setState ('filter.created_by' , $user -> id );
				
		if( $opportunity_id ){
			$this ->setState('filter.opportunity_id', $opportunity_id);
		}
		
		$responses = $this->getItems();
		
		return $responses;
	}
	
    public function getItemsByOpportunity( $opportunity = null )
    {
		$user = JFactory::getUser();
		
		if( $user -> guest){
			return null;
		}

		if( ! $opportunity -> id ){
			return null;
		}
		
		$donorwizUser = new DonorwizUser( $user -> id );
		$isBeneficiary = $donorwizUser -> isBeneficiary('com_donorwiz');

		//$this -> setState('filter.opportunity_id','-1');
		$this -> setState('list.ordering', 'created');
        $this -> setState('list.direction', 'desc');
		$this -> setState('filter.opportunity_id', $opportunity -> id );
		
		//Check if user can create Response item, return only his responses-------------------
		$canCreateResponse = $user->authorise('core.create', 'com_dw_opportunities_responses');
		if( $canCreateResponse ){
			$this ->setState('filter.created_by', $user -> id );
		}
		
		//Check if user can edit opportunity --------------------------------------------------
		$canEditOpportunity = $user->authorise('core.edit', 'com_dw_opportunities');
		if (!$canEditOpportunity && $user->authorise('core.edit.own', 'com_dw_opportunities')) {
			$canEditOpportunity = $user->id == $opportunity->created_by;
		}
		
		if( !$canEditOpportunity ){
			return null;
		}
		
		$responses = $this->getItems();

		$statistics = $this -> getItemsStatistics( $responses );
		
		//if( $user -> guest || !$isBeneficiary || !$opportunity->canEdit)
			//$responses = null;
			
		
		return array( 'items'=> $responses , 'statistics' => $statistics );
		
    }

	
	public function getItemsStatistics( $items ){
		
		$statistics = array();
		
		$statistics['pending'] = 0;
		$statistics['accepted'] = 0;
		$statistics['declined'] = 0;
		
		foreach ( $items as $key => $response )
		{
			if( $response -> status == 'pending' || !$response -> status )
				$statistics['pending']++;
			
			if($response->status=='accepted')
				$statistics['accepted']++;			

			if($response->status=='declined')
				$statistics['declined']++;
		}
		
		$statistics['total'] = count($items);		
		
		return $statistics;
	
	}
	
	public function getCount( $opportunity_id = null )
    {
			$db = JFactory::getDbo();
			$query = $db->getQuery(true);
			$query->select('COUNT(*)');
			$query->from($db->quoteName('#__dw_opportunities_responses'));
			$query->where('state IN ( 0 , 1 )');
			
			if( $opportunity_id )
				$this->setState('filter.opportunity_id', $opportunity_id);
			
			//Filtering opportunity_id
			$filter_opportunity_id = $this->state->get("filter.opportunity_id");
			if ($filter_opportunity_id) {
				$query->where("opportunity_id = '".$db->escape($filter_opportunity_id)."'");
			}

			// Reset the query using our newly populated query object.
			$db->setQuery($query);
			$count = $db->loadResult();
			
			return $count;
    }
	
	/**
     * Overrides the default function to check Date fields format, identified by
     * "_dateformat" suffix, and erases the field if it's not correct.
     */
    protected function loadFormData()
    {
        $app = JFactory::getApplication();
        $filters = $app->getUserState($this->context . '.filter', array());
        $error_dateformat = false;
        foreach ($filters as $key => $value)
        {
            if (strpos($key, '_dateformat') && !empty($value) && !$this->isValidDate($value))
            {
                $filters[$key] = '';
                $error_dateformat = true;
            }
        }
        if ($error_dateformat)
        {
            $app->enqueueMessage(JText::_("COM_PRUEBA_SEARCH_FILTER_DATE_FORMAT"), "warning");
            $app->setUserState($this->context . '.filter', $filters);
        }

        return parent::loadFormData();
    }

    /**
     * Checks if a given date is valid and in an specified format (YYYY-MM-DD) 
     * 
     * @param string Contains the date to be checked
     * 
     */
    private function isValidDate($date)
    {
        return preg_match("/^(19|20)\d\d[-](0[1-9]|1[012])[-](0[1-9]|[12][0-9]|3[01])$/", $date) && date_create($date);
    }

}