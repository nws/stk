<?php

class user extends model {
	static $boolean_fields = array(
		'want_emails',
	);

	// if login unknown: return null
	// if pass bad, return false
	// return uid
	protected function get_user_id_by_email_pass($email, $pass) {
		$this->used_tables(array('user'));
		$u = sel::from('user')
			->fields(array(
				'password',
			))
			->where('LOWER(email) = LOWER(?)', $email)
			->exec_one();
		if (!$u || empty($u['password'])) {
			return null;
		}
		if (userlib::check_pass($pass, $u['password'])) {
			return $u['user_id'];
		}
		return false;
	}

	protected function create($rec) {
		$this->is_destructive(array('user'));
		if (isset($rec['password'])) {
			$rec['password'] = userlib::encrypt_pass($rec['password']);
		}

		$last_name_parts = explode(' ', $rec['last_name']);

		$rec['name'] = ucfirst($rec['first_name'])
			.' '
			.strtoupper(substr(array_pop($last_name_parts), 0, 1));

		$rec['!last_active_date'] = 'DATE(NOW())';
		$rec['!update_dt'] = $rec['!register_dt'] = 'NOW()';

		foreach (self::$boolean_fields as $bf) {
			if (array_key_exists($bf, $rec)) {
				$rec[$bf] = intval($rec[$bf]);
			}
		}

		return mod::insert('user')
			->values($rec)
			->exec();
	}

	protected function gen_name($user_id) {
		$user = $this->get_by_user_id($user_id);
		if ($user) {

			$rec = array();
			$last_name_parts = explode(' ', $user['last_name']);
			$rec['name'] = ucfirst($user['first_name'])
				.' '
				.strtoupper(substr(array_pop($last_name_parts), 0, 1));

			return $this->edit($user_id, $rec);
		}
	}

	protected function get_namepic_mass($user_ids) {
		$names = array(); 

		if (!empty($user_ids)) {
			$names = sel::from('user')
				->fields('name', 'picture')
				->where_keys('user_id IN (?)', $user_ids)
				->exec();
		}
		// return make_hash($names, 'user_id', 'name');
		return $names;
	}

	protected function get_name_mass($user_ids) {
		$names = sel::from('user')
			->fields('name')
			->where_keys('user_id IN (?)', $user_ids)
			->exec();
		return make_hash($names, 'user_id', 'name');
	}

	protected function edit($user_id, $rec) {
		$this->is_destructive(array('user'));
		$rec['!update_dt'] = 'NOW()';
		if (!empty($rec['password'])) {
			$rec['password'] = userlib::encrypt_pass($rec['password']);
		}
		$rv = mod::update('user')
			->values($rec)
			->where('user_id = ?', $user_id)
			->exec();
		if ($rv && (isset($rec['first_name']) || isset($rec['last_name']))) {
			$this->gen_name($user_id);
		}
		return $rv!==false;
	}

	protected function get_by_user_id($user_id) {
		$this->used_tables(array('user'));
		$rec = sel::from('user')
			->fields(array(
				'email',
				'name',
				'first_name',
				'last_name',
				'picture',
				'birth_dt',
				'register_dt',
				'update_dt',
				'zip',
				'want_emails',
				'link_facebook',
				'link_linkedin',
				'link_twitter',
				'link_homepage',
				'about',
				'last_active_date',
				'howdidyouhearaboutus',
				'show_intro'
			))
			->where('user_id = ?', $user_id)
			->exec_one();
		// postprocess url fields
		foreach (array( 'link_facebook','link_linkedin','link_twitter','link_homepage') as $f) {
			if (!isset( $rec[$f] )) continue;
			if (empty( $rec[$f] )) continue;
			if (substr( $rec[$f] , 0 , 4 )!='http') $rec[$f]='http://'.$rec[$f];
		}
		return $rec;
	}

	protected function update_last_active_date( $user_id ) {
		mod::update( 'user')->values( array( 'last_active_date' => date( 'Y-m-d' ) ) )->where( 'user_id = ?',$user_id )->exec();
	}

	protected function delete($user_id) {
		$this->is_destructive(array('user'));
		return mod::delete('user')
			->where('user_id = ?',$user_id)
			->exec();
	}

	protected function get_user_id_by_email($email) {
		$this->used_tables(array('user'));
		$u = sel::from('user')
			->fields('user_id')
			->where('email = ?', $email)
			->exec_one();
		return $u ? $u['user_id'] : null;
	}

	protected function regen_pass_by_email($email) {
		$this->is_destructive(array('user'));
		if ($user_id = $this->get_user_id_by_email($email)) {
			$new_pass = userlib::pwgen();
			$enc_pw = userlib::encrypt_pass($new_pass);
			$rv = mod::update('user')
				->values(array('password' => $enc_pw))
				->where('user_id = ?', $user_id)
				->exec();
			if ($rv) {
				return $new_pass;
			}
		}
		return null;
	}

	protected function is_valid_user_id($user_id) {
		return sel::from('user')
			->fields()
			->where('user_id = ?', $user_id)
			->count();
	}

	// 
	protected function follow($user_id, $follow_id) {
		$this->is_destructive(array('follow'));
		$rv = mod::insert('follow')
			->values(array(
				'user_id' => $user_id,
				'follow_id' => $follow_id,
				'!follow_dt' => 'NOW()',
			))
			->exec();
		if ($rv) {
			m('wall')->add_for('follow', $user_id, array(
				'action' => 'follow',
				'follow_id' => $follow_id,
				'user_id' => $user_id,
			));
		}
		return $rv;
	}
	
	protected function unfollow($user_id, $follow_id) {
		$this->is_destructive(array('follow'));
		$rv = mod::delete('follow')
			->where('user_id=?', $user_id)
			->where('follow_id=?', $follow_id)
			->exec();
	}

	protected function is_following($user_id, $follow_id) {
		$this->used_tables(array('user'));
		return !!sel::from('follow')
			->fields()
			->where('user_id = ?', $user_id)
			->where('follow_id = ?', $follow_id)
			->count();
	}

	protected function get_followers_of($user_id, $crit=array()) {
		$sel = array();
		if (in_array('with_pictures',$crit)) {
			$sel = sel::from('follow')
				->fields('!user.user_id','!user.name','!user.picture')
				->join('user', 'user.user_id=follow.user_id')
				->where('follow_id = ?', $user_id)
				->exec();
		} else {
			$sel = sel::from('follow', sel::NO_ID_TABLE)
				->fields('user_id')
				->where('follow_id = ?', $user_id)
				->exec('user_id');
		}
		return $sel;
	}

	protected function get_follower_count_of( $user_id ) {
		return sel::from('follow')->where( 'user_id = ?' , $user_id )->count();
	}

	protected function get_leaders_of($user_id, $crit=array()) {
		$sel = array();
		if (in_array('with_pictures',$crit)) {
			$sel = sel::from('follow')
				->fields('!user.name','!user.picture')
				->join('user', 'user.user_id=follow.follow_id')
				->where('follow.user_id = ?', $user_id)
				->exec();
		} else {
			$sel = sel::from('follow', sel::NO_ID_TABLE)
				->fields('follow_id')
				->where('user_id = ?', $user_id)
				->exec('follow_id');
		}
		return $sel;
	}
	protected function find() {
		return sel::from('user')
			->fields('name')
			->where(1)
			->exec();
	}

	protected function load( $user_id ) {
		return sel::from('user')
			->fields( '!user.*' )
			->where('user_id=?',$user_id )
			->exec_one();
	}

	protected function search( $crit = array() ) {
		$user_id = stash()->user_id;
		$sel = sel::from( 'user' );
		$sel->fields( 'name' , 'picture' );
		$sel->where(1);
		if (isset( $crit['filter'] ))
			$sel->where( "name like ?" , '%'.$crit['filter'] . '%' );
		
		if (isset( $crit['limit'] ))
			$sel->limit( $crit['limit'] );

		$retval = $sel->exec();
		// get followeds
		if (isset( $user_id )) {
			$fsel = sel::from( 'follow' )->fields( 'follow_id' )->where( 'user_id = ?' , $user_id )->exec();
			// create fol
			$followeds = array();	
			foreach ($fsel as $row) $followeds[ $row['follow_id'] ] = true;
		}
		// attach urls
		foreach ($retval as $i => &$user) {
			$user['url']='/journal/'.$user['user_id'];
			$user['img_smallthumb'] = img::get_url( $user['picture'] , 'micro' );
			if (isset( $user_id )) {
				// only follow the unfolloweds
				if (!isset( $followeds[ $user['user_id'] ] ))
					$user['follow_url'] = '/api/follow/put/'.$user['user_id'];
			}
		}

                if (!empty( $retval )) {
                        // initialize default follower counts
                        $len = count( $retval );
                        for ($i=0;$i<$len;$i++) $retval[$i]['followers']=0;

                        // "join" followers
                        $idxs = array();
                        $user_ids = array();
                        foreach ($retval as $i => $row) {
                                $idxs[$row['user_id']]=$i;
                                $user_ids[]=$row['user_id'];
                        }
                        $sel = sel::from('follow',sel::NO_ID_TABLE)->fields( 'user_id','!count(*) as num' );
                        $sel->where( sprintf( 'user_id in (%s)' , implode( ',' , $user_ids ) ) );
                        $sel->group_by( 'user_id' );
                        $followers = $sel->exec();
                        foreach ($followers as $fol) {
                                $retval[ $idxs[ $fol['user_id'] ] ]['followers'] = $fol['num'];
			}
		}
		return $retval;
	}

	protected function stats_get_users_num( $crit = array() ) {
		$sel = sel::from('user')->where(1);
		if (isset( $crit['from_date'] ))
			$sel->where( 'register_dt>= ?',$crit['from_date'] );
		if (isset( $crit['to_date'] ))
			$sel->where( 'register_dt<= ?',$crit['to_date'] );
		if (isset( $crit['only_active'] ) && $crit['only_active']) {
			if (isset( $crit['from_date'] ) && isset( $crit['to_date'] )) {
				// start & end
				$sel->where( "(( ? > register_dt and ? < last_active_date) or ( ? > last_active_date))",
					$crit['from_date'],
					$crit['to_date'],
					$crit['to_date'] );
			} else if (isset( $crit['from_date'] )) {
				$sel->where( "? < last_active_date" , $crit['from_date'] );
				// just start date
			} else if (isset( $crit['to_date'] )) {
				// just end date
				$sel->where( "? > last_active_date" , $crit['to_date'] );
			}
		}
		if (isset( $crit['add_info'] )) {
			$sel->where( "(
				(link_facebook is not null and link_facebook<>'') or
				(link_linkedin is not null and link_linkedin<>'') or
				(link_twitter is not null and link_twitter<>'') or
				(link_homepage is not null and link_homepage<>'') or
				(about not null and about<>''))" );
		}
		return $sel->count();
	}

	protected function stats_get_users_num_by_age_group() {
		$ds = sel::from('user',sel::NO_ID_TABLE)->where(1)->fields( array(
			'!FLOOR((TO_DAYS(NOW())- TO_DAYS(birth_dt)) / 365.25) as age',
			 '!count(*) as num' )
		)->group_by( 'age' )->exec();
		$agegroups = array();
		$agegroups[]=array(13,17,'13-17');
		$agegroups[]=array(18,24,'18-24');
		$agegroups[]=array(25,34,'25-34');
		$agegroups[]=array(35,44,'35-44');
		$agegroups[]=array(45,54,'45-54');
		$agegroups[]=array(55,150,'55+');

		$retval = array();
		foreach ($agegroups as $i => $ag) {
			$a['title']=$ag[2];
			$a['num'] = 0;
			$retval[]=$a;
		}
		foreach ($ds as $row) {
			foreach ($agegroups as $i => $ag) {
				if ($row['age']<$ag[0]) continue;
				if ($row['age']>$ag[1]) continue;
				$retval[$i]['num']+=$row['num'];
				break;
			}
		}
		return $retval;
	}
}
