<?php
/**
 * CDbMessageSource class file.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @link http://www.yiiframework.com/
 * @copyright Copyright &copy; 2008-2009 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

/**
 * CDbMessageSource represents a message source that stores translated messages in database.
 *
 * The database must contain the following two tables:
 * <pre>
 * CREATE TABLE SourceMessage
 * (
 *     id INTEGER PRIMARY KEY,
 *     category VARCHAR(32),
 *     message TEXT
 * );
 * CREATE TABLE Message
 * (
 *     id INTEGER,
 *     language VARCHAR(16),
 *     translation TEXT,
 *     PRIMARY KEY (id, language),
 *     CONSTRAINT FK_Message_SourceMessage FOREIGN KEY (id)
 *          REFERENCES SourceMessage (id) ON DELETE CASCADE ON UPDATE RESTRICT
 * );
 * </pre>
 * The 'SourceMessage' table stores the messages to be translated, and the 'Message' table
 * stores the translated messages. The name of these two tables can be customized by setting
 * {@link sourceMessageTable} and {@link translatedMessageTable}, respectively.
 *
 * When {@link cachingDuration} is set as a positive number, message translations will be cached.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @version $Id$
 * @package system.i18n
 * @since 1.0
 */
class CDbMessageSource extends CMessageSource
{
	const CACHE_KEY_PREFIX='Yii.CDbMessageSource.';
	/**
	 * @var string the ID of the database connection application component. Defaults to 'db'.
	 */
	public $connectionID='db';
	/**
	 * @var string the name of the source message table. Defaults to 'SourceMessage'.
	 */
	public $sourceMessageTable='SourceMessage';
	/**
	 * @var string the name of the translated message table. Defaults to 'Message'.
	 */
	public $translatedMessageTable='Message';
	/**
	 * @var integer the time in seconds that the messages can remain valid in cache.
	 * Defaults to 0, meaning the caching is disabled.
	 */
	public $cachingDuration=0;

	private $_db;

	/**
	 * Initializes the application component.
	 * This method overrides the parent implementation by preprocessing
	 * the user request data.
	 */
	public function init()
	{
		parent::init();
		if(($this->_db=Yii::app()->getComponent($this->connectionID)) instanceof CDbConnection)
			$this->_db->setActive(true);
		else
			throw new CException(Yii::t('yii','CDbMessageSource.connectionID is invalid. Please make sure "{id}" refers to a valid database application component.',
				array('{id}'=>$this->connectionID)));
	}

	/**
	 * Loads the message translation for the specified language and category.
	 * @param string the message category
	 * @param string the target language
	 * @return array the loaded messages
	 */
	protected function loadMessages($category,$language)
	{
		if($this->cachingDuration>0 && ($cache=Yii::app()->getCache())!==null)
		{
			$key=self::CACHE_KEY_PREFIX.'.messages';
			if(($data=$cache->get($key))!==false)
				return unserialize($data);
		}

		$sql=<<<EOD
SELECT t1.message AS message, t2.translation AS translation
FROM {$this->sourceMessageTable} t1, {$this->translatedMessageTable} t2
WHERE t1.id=t2.id AND t1.category=:category AND t2.language=:language
EOD;
		$command=$this->_db->createCommand($sql);
		$command->bindValue(':category',$category);
		$command->bindValue(':language',$language);
		$rows=$command->queryAll();
		$messages=array();
		foreach($rows as $row)
			$messages[$row['message']]=$row['translation'];

		if(isset($cache))
			$cache->set($key,serialize($messages),$this->cachingDuration);

		return $messages;
	}
}