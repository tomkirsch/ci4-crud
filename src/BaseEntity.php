<?php namespace Tomkirsch\Crud;

use CodeIgniter\Entity;

class BaseEntity extends Entity{
	
	// apply timezones to non-empty dates. Note that you do NOT need to do this with <input type="datetime-local">
	public function applyTimezone(string $timezone, array $attributes=[]){
		// apply casts to make Time instances
		foreach($this->toArray() as $key=>$val){
			if(!is_a($val, 'CodeIgniter\I18n\Time')) continue;
			if(!empty($attributes) && !in_array($key, $attributes)) continue;
			$this->$key = $val->setTimezone($timezone);
		}
		return $this;
	}
	public function applyLocalTimezone(array $attributes=[]){
		$config = config('App');
		return $this->applyTimezone($config->localTimezone, $attributes);
	}
	public function applyServerTimezone(array $attributes=[]){
		$config = config('App');
		return $this->applyTimezone($config->appTimezone, $attributes);
	}
	
	// map GROUP_CONCAT fields to an array of objects (or Entities)
	// ex: $users = $entity->csvMap(['user_ids'=>'user_id', 'user_emails'=>'user_email'], 'App\Entities\User');
	public function csvMap(array $map, string $className='object', string $separator=','){
		$temp = [];
		$longest = 0;
		foreach($map as $attr => $newProp){
			if(!isset($temp[$newProp])) $temp[$newProp] = [];	
			if(empty($this->attributes[$attr])){
				continue;
			}
			$val = $this->attributes[$attr];
			if(!is_array($val)) $val = explode($separator, $val);
			// do NOT use array merge!
			$temp[$newProp] += $val;
			if(count($temp[$newProp]) > $longest) $longest = count($temp[$newProp]);
		}
		$result = [];
		for($i=0; $i<$longest; $i++){
			$obj = new $className();
			foreach($temp as $prop => $values){
				$obj->{$prop} = $values[$i] ?? NULL;
			}
			if(is_a($obj, '\CodeIgniter\Entity')){
				$obj->syncOriginal(); // we assume the data comes from the DB and thus hasn't been changed
			}
			$result[] = $obj;
		}
		return $result;
	}
	
	// find all attributes with prefix(es) and return a new entity with those attributes
	// ex: $image = $entity->prefixMap(['image_', 'upload_'], '\App\Entities\Image');
	public function prefixMap($prefixes, string $className='object', $removePrefix=FALSE){
		if(!is_array($prefixes)){
			$prefixes = [$prefixes];
		}
		// generate entity attributes
		$attr = [];
		foreach($prefixes as $prefix){
			$prefixLen = strlen($prefix);
			foreach($this->attributes as $key=>$val){
				if(substr($key, 0, $prefixLen) === $prefix){
					if($removePrefix){
						$key = substr($key, $prefixLen);
					}
					$attr[$key] = $val;
				}
			}
		}
		$entity = new $className();
		if(is_a($entity, '\CodeIgniter\Entity')){
			$entity->setAttributes($attr); // using this method makes the attributes "original", ie. not changed from database
		}
		return $entity;
	}
	
	public function stripAliasPrefix(string $aliasPrefix){
		$prefixLen = strlen($aliasPrefix);
		foreach($this->attributes as $attr=>$val){
			if(substr($attr, 0, $prefixLen) === $aliasPrefix){
				$this->attributes[substr($attr, $prefixLen)] = $val;
				unset($this->attributes[$attr]);
			}
		}
		$this->syncOriginal();
	}
}