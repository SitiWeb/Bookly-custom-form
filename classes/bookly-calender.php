<?php
class bookly_calender extends bookly_sw_custom{

    public function color_array(){
        $services = $this->get_services();
        if ($services){
            return $this->create_new_array($services);
        }
        return False;
    }
    
    public function create_new_array($original_array) {
        $new_array = array();
        foreach ($original_array as $item) {
          $new_array[$item['id']] = $item['color'];
        }
        return $new_array;
    }
}