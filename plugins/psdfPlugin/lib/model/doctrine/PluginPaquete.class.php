<?php

/**
 * This class has been auto-generated by the Doctrine ORM Framework
 */
abstract class PluginPaquete extends BasePaquete
{
	// Dom xpdl
	var $xml = null;
	
  public function __toString(){
    return $this->getNombre();
  }

  /**
   * Genera o Actualiza un Package Xpdl en el atributo Xpdl.
   * Si es la primera vez lo va a generar desde la plantilla
   * En las veces sucesivas va a intentar actualizar label, name
   * @return unknown_type
   */
  public function updateXpdlPackage()
  {
  	if( !$this->getXpdl() )
  	{
      // No tiene xpdl asignado aun, lo genero
      // a partir del esqueleto
      
  		$arguments['package_id'] = $this->getXpdlId();
	    $arguments['package_name'] = $this->getXpdlName();
      $arguments['package_display_name'] = $this->getNombre();
	    $arguments['plantilla'] = 'default';
	    $gxp = new psdfGenerateXpdlPackage();
	    $xpdl = $gxp->execute($arguments, $options);
      $this->setXpdl($xpdl);	
  	}
  	else
  	{
	    // Actualizo el xpdl con el Id y Nombre si hubiese cambiado.
	    // El id solo la primera vez, luego ya no seria modificado.
	    
	    if( !$this->xpdlLoad() ) 
	      throw new sfException(sprintf('No se pudo cargar xml/xpdl del paquete '.$this->getNombre()));   
	
	    $xp = new domxpath( $this->xml );    
	    $nodeList = $xp->query( "/xpdl2:Package" );	    
	    $node = $nodeList->item(0);
	    if($node->getAttribute('Id')=='_')
        $node->setAttribute('Id', $this->getXpdlId());
	    $node->setAttribute('Name', $this->getXpdlName());
	    $node->setAttribute('xpdExt:DisplayName', $this->getNombre());
	    
	    $xpdl = $this->xml->saveXML();
	    $this->setXpdl($xpdl);  		
  	}
  }

  /**
   * Genera o Actualiza un Process Xpdl en el atributo Xpdl.
   * Si es la primera vez lo va a generar desde la plantilla
   * En las veces sucesivas va a intentar actualizar label, name
   * @return unknown_type
   */
  public function updateXpdlProcess($procesoXpdlId, $procesoXpdlName, $procesoXpdlDisplayName)
  {
    if( !$this->xpdlLoad() ) 
      throw new sfException(sprintf('No se pudo cargar xml/xpdl del paquete '.$this->getNombre()));   

    $xp = new domxpath( $this->xml );
      
    // Verifico ya no exista el proceso
    if( !$this->xpdlProcessExist($procesoXpdlId))
    {    	
      // No existe aun, genero el proceso a partir del esqueleto      
    	
      $arguments['process_id'] = $procesoXpdlId;
      $arguments['process_name'] = $procesoXpdlName;
      $arguments['process_display_name'] = $procesoXpdlDisplayName;
      $options = array();
      $gxp = new psdfGenerateXpdlProcess();
      $contentProc = $gxp->execute($arguments, $options);

      $gxp = new psdfGenerateXpdlPool();
      $arguments['process_id'] = $procesoXpdlId;
      $contentPool = $gxp->execute($arguments, $options);
      
      // Ahora inserto ese proceso en el paquete Xpdl
      // Como tratamiento de string es suficiente
      
      $contentPack = $this->getXpdl();
      
      // Pool      
      // Defino etiqueta Pools si es el primero
      $pos = strpos($contentPack, '<xpdl2:Pools>');
      if($pos<=0)
      {
        $key = '</xpdl2:RedefinableHeader>';
        $contentPack = str_replace($key, $key.'<xpdl2:Pools></xpdl2:Pools>', $contentPack);
      }
      // Inserto al final de pools
      $key = '</xpdl2:Pools>';
      $contentPack = str_replace($key, $contentPool.$key, $contentPack);
      
      // Proceso      
      // Defino etiqueta WorkflowProcesses si es el primero
      $pos = strpos($contentPack, '<xpdl2:WorkflowProcesses>');
      if($pos<=0)
      {
        $key = '<xpdl2:ExtendedAttributes>';
        $contentPack = str_replace($key, '<xpdl2:WorkflowProcesses></xpdl2:WorkflowProcesses>'.$key, $contentPack);
      }
      // Inserto al final de WorkflowProcesses
      $key = '</xpdl2:WorkflowProcesses>';
      $contentPack = str_replace($key, $contentProc.$key, $contentPack);
      
      $this->setXpdl($contentPack);      
    }
    else
    {
      // Actualizo el proceso xpdl con el Nombre si hubiese cambiado.
      // El id solo la primera vez, luego ya no seria modificado.
      
      if( !$this->xpdlLoad() ) 
        throw new sfException(sprintf('No se pudo cargar xml/xpdl del paquete '.$this->getNombre()));   
  
      $nodeList = $xp->query( "/xpdl2:Package/xpdl2:WorkflowProcesses/xpdl2:WorkflowProcess[ @Id=\"".$procesoXpdlId."\"]" );     
      $node = $nodeList->item(0);
      if( $node->getAttribute('Id')=='_' )
        $node->setAttribute('Id', $procesoXpdlId);
      $node->setAttribute('Name', $procesoXpdlName);
      $node->setAttribute('xpdExt:DisplayName', $procesoXpdlDisplayName);
      
      $xpdl = $this->xml->saveXML();
      $this->setXpdl($xpdl);      
    }
  }  
  
  /**
   * Borra del xpdl el nodo proceso del parametro
   * Esta metodo debe llamarse cada vez que se de de baja un objeto proceso 
   * @param $procesoId
   * @return unknown_type
   */
  public function deleteXpdlProcess($procesoXpdlId)
  {
    if( !$this->xpdlLoad() ) 
      throw new sfException(sprintf('No se pudo cargar xml/xpdl del paquete '.$this->getNombre()));   

    $xp = new domxpath( $this->xml );
    $nodeList = $xp->query( "/xpdl2:Package/xpdl2:WorkflowProcesses/xpdl2:WorkflowProcess[ @Id=\"".$procesoXpdlId."\"]" );     
    
    if( $nodeList->length==1 )
    {
    	$parent = $this->xml->documentElement->getElementsByTagName('WorkflowProcesses'); 
    	$parent->item(0)->removeChild($nodeList->item(0));
    }
  }
  
  public function preSave($event)
  {
    $this->updateXpdlPackage();
  }  
  
  /**
   * Genera la estructura del paquete incluyendo xpdl
   * Si no tiene un paquete padre asociado lo genera como aplicativo 
   * Si tiene un paquete padre asociado lo genera como modulo
   * @return unknown_type
   */
  public function generate()
  {  	
  	if( !$this->getRelPaquete() )
  	{
	    $arguments['application'] = $this->getXpdlName();
	    $options['csrf-secret'] = 'UniqueSecret';
	    $options['escaping-strategy'] = 'true';
	    $app = new psdfGenerateApp();
	    $app->execute($arguments, $options);
	    unset($app);       
  	}
  	else
  	{
      $pack = $this->getMacroPaquete();  		
      $arguments['application'] = $pack->getXpdlName();
      $arguments['module'] = $this->getXpdlName();
      $options = array();
      $app = new psdfGeneratePackage();
      $app->execute($arguments, $options);
      unset($app);       
   	}  	  	
  }  
  
  /**
   * Obtiene el objeto Paquete Macro (primer nivel)
   * Ojo que puede no ser el mismo que el paquete padre
   * Si no lo tiene retorna false/0
   * @return unknown_type
   */
  public function getMacroPaquete()
  {
  	$parentId = $this->getRelPaquete();
  	if($parentId>0)
  	{
	  	while( $parentId>0 )
	  	{
		    $pack = Doctrine::getTable('Paquete')->find($parentId);
		    $parentId = $pack->getRelPaquete();	    
	    }	
	    return $pack;
  	}
  	else
  	{
  		return false;
  	}
  }  
  
  /**
   * Valida si el proceso ya existe en el paquete
   * Puede recibir 
   *   el Id objeto Proceso (123)
   *   el Id Xpdl           (_123)
   *   el Nombre            (ejemplo1)
   * @param $processId
   * @return boolean
   */
  public function xpdlProcessExist($process)
  {  
  	$filter = sprintf('@Name = "%s"', $process);
  	
  	if( is_numeric($process) )
      $filter = sprintf('@Id = "%s"', "_".$process);
      
    if( substr($process, 0, 1)=="_" )
      $filter = sprintf('@Id = "%s"', $process);
    
    $xp = new domxpath( $this->xml );    
    $nodeList = $xp->query( "/xpdl2:Package/xpdl2:WorkflowProcesses/xpdl2:WorkflowProcess[ ".$filter." ]" );

    if( $nodeList->length > 0 )
      return true;
    else
      return false;
  }
    
  /**
   * Construccion de procesos del paquete. 
   * Puede hacerlo solamente para el especificado en el parametro o 
   * para todos los contenidos en el paquete (pProcessId=0)
   * @param $pProcessId       Id del proceso (Opcional)
   * @return unknown_type
   */
  public function build($pProcessId=0)
  {  	
  	// Cargo el xml
  	if( !$this->xpdlLoad() ) 
      throw new sfException(sprintf('No se pudo cargar xml/xpdl del paquete '.$this->getNombre()));  	

    // Si tiene Macro y aún no fué implementado lo genero ahora
    if( $this->getRelPaquete()>0 )
    {
      $macro = $this->getMacroPaquete();
      if( !$macro->isImplemented())
        $macro->generate();
    }
         
    // Si el Paquete aún no fué implementado, lo genero ahora
    if( !$this->isImplemented() )
      $this->generate(); 

    // Continúo con el/los proceso/s en sí
      
    // Obtengo array typeDeclarations
    $typeDeclarations = $this->xpdlGetTypeDeclarations();
  	 
  	 // Obtengo array datafields
    $dataFields = $this->xpdlGetDataFields();
        
    // Obtengo array participantes
    $participants = $this->xpdlGetParticipants();
    
    // Obtengo name xpdl del macro paquete
    $macroXpdlName = $this->getMacroPaquete()->getXpdlName(); 
    
    // Delego en el proceso su construccion
    // Recorro el proceso parametrizado o todos si es omitido
    if($pProcessId>0)
      $procList[] = Doctrine::getTable('Proceso')->find($pProcessId);
    else 
      $procList = $this->getProcesos();
      
    foreach( $procList as $proceso )
    	$proceso->build($macroXpdlName, $this->getXpdlName(), $this->xml, $typeDeclarations, $dataFields, $participants);
  }
  
  public function xpdlLoad()
  {
  	$this->xml = new DOMDocument();   
    $ret = $this->xml->loadXML($this->getXpdl());   
    if( !$ret )
      return false;
    return true;
  }
  
  public function xpdlGetTypeDeclarations()
  {
    $typeDeclarations = array();
    $xp = new domxpath( $this->xml );
    
    $nodeList = $xp->query( "/xpdl2:Package/xpdl2:TypeDeclarations/xpdl2:TypeDeclaration" );
    foreach ( $nodeList as $node )
    {
      $id = $node->getAttribute( "Id" );
      $name = $node->getAttribute( "Name" );
      
      $description = null;
      if( $node->getElementsByTagName("Description")->length > 0 )
        $description = $node->getElementsByTagName("Description")->item(0)->textContent;

      // Tipo de dato basico
      $type = null;
      $length = null;
      $decimal = null;
      if( $node->getElementsByTagName("BasicType")->length > 0 )
      {
        $type = $node->getElementsByTagName("BasicType")->item(0)->getAttribute("Type");
        if( $node->getElementsByTagName("BasicType")->item(0)->getElementsByTagName("Length")->length > 0)
          $length = $node->getElementsByTagName("BasicType")
                    ->item(0)->getElementsByTagName("Length")->item(0)->textContent;
        if( $node->getElementsByTagName("BasicType")->item(0)->getElementsByTagName("Precision")->length > 0)
          $length = $node->getElementsByTagName("BasicType")
                    ->item(0)->getElementsByTagName("Precision")->item(0)->textContent;
        if( $node->getElementsByTagName("BasicType")->item(0)->getElementsByTagName("Scale")->length > 0)
          $decimal = $node->getElementsByTagName("BasicType")
                    ->item(0)->getElementsByTagName("Scale")->item(0)->textContent;
      }
      
      // Tipo de dato declarativo
      $declaredType = null;
      if( $node->getElementsByTagName("DeclaredType")->length > 0 )
      {
        $type = 'DeclaredType';
        $declaredType = $node->getElementsByTagName("DeclaredType")->item(0)->getAttribute('Id'); 
      }
      
      // Referencia Externa
      $externalReference = null;
      if( $node->getElementsByTagName("ExternalReference")->length > 0 )
      {
        $type = 'ExternalReference';
        $externalReference = 
          $node->getElementsByTagName("ExternalReference")->item(0)->getAttribute('location') . '|' . 
          $node->getElementsByTagName("ExternalReference")->item(0)->getAttribute('namespace') . '|' .
          $node->getElementsByTagName("ExternalReference")->item(0)->getAttribute('xref'); 
      }
             
      $typeDeclarations[$id] = array(
        'name' => $name,
        'description' => $description,
        'type' => $type,
        'length' => $length,
        'decimal' => $decimal,
        'externalReference' => $externalReference,      
        'declaredType' => $declaredType,
      );
    }
    return $typeDeclarations;  	
  }
  
  public function xpdlGetDataFields()
  {
    $dataFields = array();
    $xp = new domxpath( $this->xml );
    
    $nodeList = $xp->query( "/xpdl2:Package/xpdl2:DataFields/xpdl2:DataField" );
    foreach ( $nodeList as $node )
    {
      $id = $node->getAttribute( "Id" );
      $name = $node->getAttribute( "Name" );
      $isArray = $node->getAttribute( "IsArray" );
      $readOnly = $node->getAttribute( "ReadOnly" );
      
      $description = null;
      if( $node->getElementsByTagName("Description")->length > 0 )
        $description = $node->getElementsByTagName("Description")->item(0)->textContent;
              
      $initialValue = null;
      if( $node->getElementsByTagName("InitialValue")->length > 0 )
        $initialValue = $node->getElementsByTagName("InitialValue")->item(0)->textContent;
        
      $dataType = null;
      if( $node->getElementsByTagName("DataType")->length > 0 )
        $dataType = $node->getElementsByTagName("DataType")->item(0);
        
      // Tipo de dato basico
      $type = null;
      $length = null;
      $decimal = null;
      if( $dataType->getElementsByTagName("BasicType")->length > 0 )
      {
        $type = $dataType->getElementsByTagName("BasicType")->item(0)->getAttribute("Type");
        if( $dataType->getElementsByTagName("BasicType")->item(0)->getElementsByTagName("Length")->length > 0)
          $length = $dataType->getElementsByTagName("BasicType")
                    ->item(0)->getElementsByTagName("Length")->item(0)->textContent;
        if( $dataType->getElementsByTagName("BasicType")->item(0)->getElementsByTagName("Precision")->length > 0)
          $length = $dataType->getElementsByTagName("BasicType")
                    ->item(0)->getElementsByTagName("Precision")->item(0)->textContent;
        if( $dataType->getElementsByTagName("BasicType")->item(0)->getElementsByTagName("Scale")->length > 0)
          $decimal = $dataType->getElementsByTagName("BasicType")
                    ->item(0)->getElementsByTagName("Scale")->item(0)->textContent;
      }
      
      // Tipo de dato declarativo
      $declaredType = null;
      if( $dataType->getElementsByTagName("DeclaredType")->length > 0 )
      {
        $type = 'DeclaredType';
        $declaredType = $dataType->getElementsByTagName("DeclaredType")->item(0)->getAttribute('Id'); 
      }
      
      // Referencia Externa
      $externalReference = null;
      if( $dataType->getElementsByTagName("ExternalReference")->length > 0 )
      {
        $type = 'ExternalReference';
        $externalReference = 
          $dataType->getElementsByTagName("ExternalReference")->item(0)->getAttribute('location') . '|' . 
          $dataType->getElementsByTagName("ExternalReference")->item(0)->getAttribute('namespace') . '|' .
          $dataType->getElementsByTagName("ExternalReference")->item(0)->getAttribute('xref'); 
      }       

      $dataFields[$name] = array(
        'id' => $id,
        'isArray' => $isArray,
        'readOnly' => $readOnly,
        'description' => $description,
        'type' => $type,
        'length' => $length,
        'decimal' => $decimal,
        'externalReference' => $externalReference,      
        'declaredType' => $declaredType,
        'initialValue' => $initialValue,
      );
    }
    return $dataFields;
  }
  
  public function xpdlGetParticipants()
  {
    $participants = array();
    $xp = new domxpath( $this->xml );
    
  	$nodeList = $xp->query( "/xpdl2:Package/xpdl2:Participants/xpdl2:Participant" );
    foreach ( $nodeList as $node )
    {
      $id = $node->getAttribute( "Id");
      $name = $node->getAttribute( "Name");
      
      $description = null;
      if( $node->getElementsByTagName("Description")->length > 0 )
        $description = $node->getElementsByTagName("Description")->item(0)->textContent;
      
      $type = null;
      if( $node->getElementsByTagName("ParticipantType")->length > 0 )
        $type = $node->getElementsByTagName("ParticipantType")->item(0)->getAttribute('Type');
        
	    $participants[$name] = array(
	        'id' => $id,
          'description' =>$description,
          'type' => $type,
	      );
    }
    return $participants;
  }
  
  /**
   * Retorna el id xpdl del paquete. El id es el mismo 
   * id del objeto con un guion bajo delante.
   * id obj  => "123"
   * id xpdl => "_123"
   * @return string
   */
  function getXpdlId()
  {
  	return '_'.$this->getId();
  }
  
  /**
   * Retorna el name xpdl del paquete. El Name es el mismo
   * nombre del objeto sin espacios blancos.
   * @return string
   */
  public function getXpdlName()
  {
  	return str_replace(' ', '', $this->getNombre());
  }
  
  /**
   * Valida si el paquete ya esta implementado.
   * Esta validacion es directamente sobre el directorio.
   * Un paquete Macro se implementa como aplicativo mediante el Name
   * Un paquete Normal se implementa como modulo mediante el Name
   * 
   * @return boolean
   */
  public function isImplemented()
  {
  	if( !$this->getRelPaquete() )
    {
      // Es un macro, valido si tiene su aplicativo creado
      
    	$app = str_replace(' ', '', $this->getNombre());
    	
      $appDir = sfConfig::get('sf_apps_dir').'/'.$app;
      if (is_dir($appDir))
        return true;
    }
    else
    {
      // Es un paquete, valido si tiene su modulo creado
      
      $app = str_replace(' ', '', $this->getMacroPaquete()->getNombre());
      $module = str_replace(' ', '', $this->getNombre());
      
      $moduleDir = sfConfig::get('sf_apps_dir').'/'.$app.'/modules/'.$module;
      if (is_dir($moduleDir))
        return true;
    }
    
    return false;
  }

  public function getNombreMacroPaquete()
  {
  	$macro = $this->getMacroPaquete();
  	if( $macro )
  	  return $macro->getNombre();
    else
      return '';
  }
  
}