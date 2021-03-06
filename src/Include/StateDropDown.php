<?php
  use EcclesiaCRM\data\States;

  class StateDropDown extends States
  {  
    function __construct() {
        parent::__construct();
    }
    
    public function getDropDown($selected_state="",$statename= "State")
    {
      $state = $statename;
      $id_input = strtolower($state)."-input";
      
      $res = "";
      $res .= '<select name="'.$state.'" class="form-control select2" id="'.$id_input.'" style="width:100%">';      
      $res .= '<option value="">'.gettext('Unassigned').'</option>';
      
      $arr = $this->getAll();
      
      if (!empty($arr)) {      
        $res .= '<option value="" disabled>--------------------</option>';
      
        foreach ($this->getAll() as $keystate => $itemstate) {
            if (!empty($keystate)) {
                $res .= '<option value="'.$keystate.'"';
                if ($selected_state == $keystate) {
                  $res .= 'selected';
                } 
            } else {
              $res .= '<option value disabled';
            }
            $res .= '>'.gettext($itemstate);
          }
        }
      $res .= '</select>';
      
      return $res;
    }
  }