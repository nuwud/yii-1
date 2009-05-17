<?php
/**
 * ModelCommand class file.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @link http://www.yiiframework.com/
 * @copyright Copyright &copy; 2008-2009 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 * @version $Id$
 */

/**
 * ModelCommand generates a model class.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @version $Id$
 * @package system.cli.commands.shell
 * @since 1.0
 */
class ModelCommand extends CConsoleCommand
{
	/**
	 * @var string the template file for the model class.
	 * Defaults to null, meaning using 'framework/cli/views/shell/model/model.php'.
	 */
	public $templateFile;

	public function getHelp()
	{
		return <<<EOD
USAGE
  model <class-name> [table-name]

DESCRIPTION
  This command generates a model class with the specified class name.

PARAMETERS
 * class-name: required, model class name. By default, the generated
   model class file will be placed under the directory aliased as
   'application.models'. To override this default, specify the class
   name in terms of a path alias, e.g., 'application.somewhere.ClassName'.

   If the model class belongs to a module, it should be specified
   as 'ModuleID.models.ClassName'.

 * table-name: optional, the associated database table name. If not given,
   it is assumed to be the model class name.

EXAMPLES
 * Generates the Post model:
        model Post

 * Generates the Post model which is associated with table 'posts':
        model Post posts

 * Generates the Post model which should belong to module 'admin':
        model admin.models.Post

EOD;
	}

	/**
	 * Execute the action.
	 * @param array command line parameters specific for this command
	 */
	public function run($args)
	{
		if(!isset($args[0]))
		{
			echo "Error: model class name is required.\n";
			echo $this->getHelp();
			return;
		}
		$className=$args[0];
		if(($pos=strrpos($className,'.'))===false)
			$basePath=Yii::getPathOfAlias('application.models');
		else
		{
			$basePath=Yii::getPathOfAlias(substr($className,0,$pos));
			$className=substr($className,$pos+1);
		}

		$tableName=isset($args[1])?$args[1]:$className;
		$classFile=$basePath.DIRECTORY_SEPARATOR.$className.'.php';
		$templateFile=$this->templateFile===null?YII_PATH.'/cli/views/shell/model/model.php':$this->templateFile;
		$list=array(
			$className.'.php'=>array(
				'source'=>$templateFile,
				'target'=>$classFile,
				'callback'=>array($this,'generateModel'),
				'params'=>array($className,$tableName),
			),
		);
		$this->copyFiles($list);
		include_once($classFile);

		echo <<<EOD

The '{$className}' class has been successfully created in the following file:
    $classFile

If you have a 'db' database connection, you can test it now with:
    \$model={$className}::model()->find();
    print_r(\$model);

EOD;
	}

	public function generateModel($source,$params)
	{
		list($className,$tableName)=$params;
		$content=file_get_contents($source);
		$rules='';
		$labels='';
		if(($db=Yii::app()->getDb())!==null)
		{
			$db->active=true;
			if(($table=$db->schema->getTable($tableName))!==null)
			{
				$required=array();
				$integers=array();
				$numerical=array();
				foreach($table->columns as $column)
				{
					$label=ucwords(trim(strtolower(str_replace(array('-','_'),' ',preg_replace('/(?<![A-Z])[A-Z]/', ' \0', $column->name)))));
					if(strcasecmp(substr($label,-3),' id')===0)
						$label=substr($label,0,-3);
					$labels.="\n\t\t\t'{$column->name}'=>'$label',";
					if($column->isPrimaryKey && $table->sequenceName!==null || $column->isForeignKey)
						continue;
					if(!$column->allowNull && $column->defaultValue===null)
						$required[]=$column->name;
					if($column->type==='integer')
						$integers[]=$column->name;
					else if($column->type==='double')
						$numerical[]=$column->name;
					else if($column->type==='string' && $column->size>0)
						$rules.="\n\t\t\tarray('{$column->name}','length','max'=>{$column->size}),";
				}
				if($required!==array())
					$rules.="\n\t\t\tarray('".implode(', ',$required)."', 'required'),";
				if($integers!==array())
					$rules.="\n\t\t\tarray('".implode(', ',$integers)."', 'numerical', 'integerOnly'=>true),";
				if($numerical!==array())
					$rules.="\n\t\t\tarray('".implode(', ',$numerical)."', 'numerical'),";
			}
			else
				echo "Warning: the table '$tableName' does not exist in the database.\n";
		}
		else
			echo "Warning: you do not have a 'db' database connection as required by Active Record.\n";

		$tr=array(
			'{ClassName}'=>$className,
			'{TableName}'=>$tableName,
			'{Labels}'=>$labels,
			'{Rules}'=>$rules);

		return strtr($content,$tr);
	}
}