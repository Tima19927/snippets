<?php

//Для  отладки отпраки заявок в sandSay
add_shortcode('sandsay',  function (){

	$sendSay = new Sendsay('LOgin', 'sublogin', 'password');
	$email = ''; // Электронная почта

	$data = array(
		'-group' => array(
			'p457' => 1 //Список подписки
		),

		'a753' => array( // ID анкеты
			'q782' => '' //ID поля => Имя
		)
	);
	$idAddedUser = $sendSay->member_set($email, $data);
	$sendSay->member_sendconfirm( $idAddedUser['member']['id'] ,$idAddedUser['member']['email'] );
});

class Sendsay {
	/**
	 * @var массив с авторизационными данными
	 */
	private $auth = array();

	/**
	 * @var параметры запроса
	 */
	private $params;

	/**
	 * @var вывод отладочной информации
	 */
	public $debug = false;

	/**
	 * Конструктор класса.
	 *
	 * @param  string  общий логин
	 * @param  string  личный логин
	 * @param  string  пароль
	 * @param  bool    вывод отладочной информации
	 */
	public function Sendsay( $login, $sublogin, $password, $debug = false ) {
		$this->debug                 = $debug;
		$this->auth['one_time_auth'] = array(
			'login'    => $login,
			'sublogin' => $sublogin,
			'passwd'   => $password
		);
	}

	/**
	 * Отправляет данные в Sendsay.
	 *
	 * @return array
	 */
	private function send($redirect = '')
	{
		if ($this->debug) {
			echo '<pre>Запрос2:'."\n".print_r(json_encode($this->params ) , TRUE)."\n</pre>";
		}

		$curl = curl_init('https://api.sendsay.ru'.$redirect.'?apiversion=100&json=1');
		curl_setopt($curl, CURLOPT_POST, TRUE);
		curl_setopt($curl, CURLOPT_POSTFIELDS, 'request='.urlencode(json_encode($this->params)));
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);

		$result = curl_exec($curl);
		$json = json_decode($result, TRUE);
		if ($this->debug)		{
			echo '<pre>Ответ '."\n". print_r($result, true) ."\n</pre>";
		}
		curl_close($curl);

		if ( ! $json)		{
			return array('error' => 'error/bad_json', 'explain' => $result);
		}

		if (array_key_exists('REDIRECT', $json))		{
			return $this->send($json['REDIRECT']);
		}

		if (isset($json['errors'])){

		}
		return $json;
	}

	/**
	 * Форматирует JSON-строку для отладки.
	 *
	 * @param  string  исходная JSON-строка
	 *
	 * @return string
	 */
	private function json_dump($json)
	{
		$result      = '';
		$pos         = 0;
		$strLen      = strlen($json);
		$indentStr   = "\t";
		$newLine     = "\n";
		$prevChar    = '';
		$outOfQuotes = TRUE;

		for ($i = 0; $i <= $strLen; $i++)
		{
			$char = substr($json, $i, 1);

			if ($char == '"' && $prevChar != '\\')
			{
				$outOfQuotes = !$outOfQuotes;
			}
			elseif (($char == '}' || $char == ']') && $outOfQuotes)
			{
				$result .= $newLine;
				$pos--;

				for ($j = 0; $j < $pos; $j++)
				{
					$result .= $indentStr;
				}
			}

			$result .= $char;

			if (($char == ',' || $char == '{' || $char == '[') && $outOfQuotes)
			{
				$result .= $newLine;

				if ($char == '{' || $char == '[')
				{
					$pos++;
				}

				for ($j = 0; $j < $pos; $j++)
				{
					$result .= $indentStr;
				}
			}

			$prevChar = $char;
		}

		return $result;
	}

	/**
	 * Добавляет значение к массиву параметров запроса.
	 *
	 * @param  string название параметра
	 * @param  mixed  значение параметра
	 */
	private function param($name, $value=NULL)
	{
		if ($value !== NULL)
		{
			$this->params[$name] = $value;
		}
	}

	/**
	 * Добавляет нового подписчика или обновляет существующего.
	 *
	 * @link  [https://pro.subscribe.ru/API/API.html#C%D0%BE%D0%B7%D0%B4%D0%B0%D1%82%D1%8C-%D0%BF%D0%BE%D0%B4%D0%BF%D0%B8%D1%81%D1%87%D0%B8%D0%BA%D0%B0-%D0%A3%D1%81%D1%82%D0%B0%D0%BD%D0%BE%D0%B2%D0%B8%D1%82%D1%8C-%D0%BE%D1%82%D0%B2%D0%B5%D1%82%D1%8B-%D0%BF%D0%BE%D0%B4%D0%BF%D0%B8%D1%81%D1%87%D0%B8%D0%BA%D0%B0][Документация]
	 *
	 * @param  string  емэйл подписчика
	 * @param  array   массив с данными подписчика
	 * @param  mixed   код шаблона письма-приветствия (int) или не высылать письмо (NULL)
	 * @param  bool    необходимость подтверждения внесения в базу
	 * @param  string  правило изменения ответов анкетных данных (error|update|overwrite)
	 * @param  string  тип адреса подписчика (email|msisdn)
	 *
	 * @return array
	 */
	public function member_set($email, $data=NULL, $notify=NULL, $confirm=FALSE, $if_exists='overwrite', $addr_type='email')
	{
		$this->params = $this->auth+array(
				'action'         => 'member.set',
				'addr_type'      => $addr_type,
				'email'          => $email,
				'source'         => $_SERVER['REMOTE_ADDR'],
				'if_exists'      => $if_exists,
				'newbie.confirm' => 1
			);

		if ($confirm)
		{
			$this->param('newbie.letter.confirm', $notify);
		}
		else
		{
			$this->param('newbie.letter.no-confirm', $notify);
		}

		$this->param('obj', $data);

		return $this->send();
	}

	public function  member_sendconfirm($id,   $email){

		$this->params = $this->auth + array(
				'action'         => 'member.sendconfirm',
				'letter'         => 122, // ID Информационного письма
				'email'          => $email,// Email адрес подписчика
				'addr_type'      => 'email',
				'list'           => array($id), //ID подписчиков
			);

		//error_log("PARAMS  ---   " . print_r($this->params,true) );

		return $this->send();
	}

	/**
	 * Создаёт анкету.
	 *
	 * @link  [https://pro.subscribe.ru/API/API.html#%D0%A1%D0%BE%D0%B7%D0%B4%D0%B0%D0%BD%D0%B8%D0%B5-%D0%B0%D0%BD%D0%BA%D0%B5%D1%82%D1%8B][Документация]
	 *
	 * @param  string  название анкеты
	 * @param  string  код анкеты
	 * @param  string  код копируемой анкеты
	 *
	 * @return array
	 */
	public function anketa_create($name, $id=NULL, $copy=NULL)
	{
		$this->params = $this->auth+array(
				'action' => 'anketa.create',
				'name'   => $name
			);

		$this->param('id', $id);
		$this->param('copy_from', $copy);

		return $this->send();
	}

	/**
	 * Изменяет название анкеты.
	 *
	 * @link  [https://pro.subscribe.ru/API/API.html#%D0%A1%D0%BE%D1%85%D1%80%D0%B0%D0%BD%D0%B5%D0%BD%D0%B8%D0%B5-%D0%B0%D0%BD%D0%BA%D0%B5%D1%82%D1%8B][Документация]
	 *
	 * @param  string  код анкеты
	 * @param  string  название анкеты
	 *
	 * @return array
	 */
	public function anketa_set($id, $name)
	{
		$this->params = $this->auth+array(
				'action' => 'anketa.set',
				'id'     => $id,
				'name'   => $name
			);

		return $this->send();
	}

	/**
	 * Добавляет вопрос в анкету.
	 *
	 * @link  [https://pro.subscribe.ru/API/API.html#%D0%94%D0%BE%D0%B1%D0%B0%D0%B2%D0%BB%D0%B5%D0%BD%D0%B8%D1%8F-%D0%BD%D0%BE%D0%B2%D0%BE%D0%B3%D0%BE-%D0%B2%D0%BE%D0%BF%D1%80%D0%BE%D1%81%D0%B0-%D0%B0%D0%BD%D0%BA%D0%B5%D1%82%D1%8B][Документация]
	 *
	 * @param  string  код анкеты
	 * @param  array   один или несколько вопросов анкеты
	 *
	 * @return array
	 */
	public function anketa_quest_add($anketa, $questions)
	{
		$this->params = $this->auth+array(
				'action'    => 'anketa.quest.add',
				'anketa.id' => $anketa,
				'obj'       => $questions
			);

		return $this->send();
	}

	/**
	 * Изменяет вопросы анкеты.
	 *
	 * @link  [https://pro.subscribe.ru/API/API.html#%D0%98%D0%B7%D0%BC%D0%B5%D0%BD%D0%B5%D0%BD%D0%B8%D0%B5-%D0%B2%D0%BE%D0%BF%D1%80%D0%BE%D1%81%D0%B0-%D0%B0%D0%BD%D0%BA%D0%B5%D1%82%D1%8B][Документация]
	 *
	 * @param  string  код анкеты
	 * @param  array   один или несколько вопросов анкеты
	 *
	 * @return array
	 */
	public function anketa_quest_set($anketa, $questions)
	{
		$this->params = $this->auth+array(
				'action'    => 'anketa.quest.set',
				'anketa.id' => $anketa,
				'obj'       => $questions
			);

		return $this->send();
	}

	/**
	 * Удаляет вопрос из анкеты.
	 *
	 * @link  [https://pro.subscribe.ru/API/API.html#%D0%A3%D0%B4%D0%B0%D0%BB%D0%B5%D0%BD%D0%B8%D0%B5-%D0%B2%D0%BE%D0%BF%D1%80%D0%BE%D1%81%D0%B0-%D0%B0%D0%BD%D0%BA%D0%B5%D1%82%D1%8B][Документация]
	 *
	 * @param  string  код анкеты
	 * @param  mixed   один (string) или несколько (array) вопросов анкеты
	 *
	 * @return array
	 */
	public function anketa_quest_delete($anketa, $questions)
	{
		$this->params = $this->auth+array(
				'action'    => 'anketa.quest.delete',
				'anketa.id' => $anketa,
				'id'        => $questions
			);

		return $this->send();
	}

	/**
	 * Изменяет порядок вопросов анкеты.
	 *
	 * @link  [https://pro.subscribe.ru/API/API.html#%D0%98%D0%B7%D0%BC%D0%B5%D0%BD%D0%B5%D0%BD%D0%B8%D0%B5-%D0%BF%D0%BE%D0%B7%D0%B8%D1%86%D0%B8%D0%B8-%D0%B2%D0%BE%D0%BF%D1%80%D0%BE%D1%81%D0%B0-%D0%B0%D0%BD%D0%BA%D0%B5%D1%82%D1%8B][Документация]
	 *
	 * @param  string  код анкеты
	 * @param  mixed   коды вопросов анкеты в нужном порядке
	 *
	 * @return array
	 */
	public function anketa_quest_order($anketa, $order)
	{
		$this->params = $this->auth+array(
				'action'    => 'anketa.quest.order',
				'anketa.id' => $anketa,
				'order'     => $order
			);

		return $this->send();
	}

	/**
	 * Изменяет порядок ответов.
	 *
	 * @link  [https://pro.subscribe.ru/API/API.html#%D0%98%D0%B7%D0%BC%D0%B5%D0%BD%D0%B5%D0%BD%D0%B8%D0%B5-%D0%BF%D0%BE%D0%B7%D0%B8%D1%86%D0%B8%D0%B8-%D0%BE%D1%82%D0%B2%D0%B5%D1%82%D0%B0-%D0%B2%D0%BE%D0%BF%D1%80%D0%BE%D1%81%D0%B0][Документация]
	 *
	 * @param  string  код анкеты
	 * @param  string  код вопроса
	 * @param  array   коды ответов в нужном порядке
	 *
	 * @return array
	 */
	public function anketa_quest_response_order($anketa, $question, $order)
	{
		$this->params = $this->auth+array(
				'action'    => 'anketa.quest.response.order',
				'anketa.id' => $anketa,
				'id'        => $question,
				'order'     => $order
			);

		return $this->send();
	}

	/**
	 * Удаляет ответ из вопроса анкеты.
	 *
	 * @link  [https://pro.subscribe.ru/API/API.html#%D0%A3%D0%B4%D0%B0%D0%BB%D0%B5%D0%BD%D0%B8%D0%B5-%D0%BE%D1%82%D0%B2%D0%B5%D1%82%D0%B0-%D0%B2%D0%BE%D0%BF%D1%80%D0%BE%D1%81%D0%B0-%D0%B0%D0%BD%D0%BA%D0%B5%D1%82%D1%8B][Документация]
	 *
	 * @param  string  код анкеты
	 * @param  string  код вопроса
	 * @param  string  код ответа
	 *
	 * @return array
	 */
	public function anketa_quest_response_delete($anketa, $question, $answer)
	{
		$this->params = $this->auth+array(
				'action'    => 'anketa.quest.response.delete',
				'anketa.id' => $anketa,
				'quest.id'  => $question,
				'id'        => $answer
			);

		return $this->send();
	}

}
