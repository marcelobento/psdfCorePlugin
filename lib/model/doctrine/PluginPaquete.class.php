<?php

/**
 * This class has been auto-generated by the Doctrine ORM Framework
 */
abstract class PluginPaquete extends BasePaquete {

    // Instancia tratamiento xpdl
    var $xpdl = false;

    public function __toString() {
        return $this->getNombre();
    }

    /**
     * Genera un XPDL en el atributo xpdl del objeto, si aún no lo tiene.
     *
     * @return boolean
     */
    public function generateXpdlPackage() {

        // No puedo generar si ya hay contenido
        if( $this->getXpdl() ) {
            return false;
        }

        // El objeto debió ser generado previamente (id>0)
        if( !$this->getId()>0 ) {
            return false;
        }

        $arguments['package_id'] = $this->getXpdlId();
        $arguments['package_name'] = $this->getXpdlName();
        $arguments['package_display_name'] = $this->getNombre();
        $arguments['package_macro_id'] = $this->getMacro()->getId();
        $arguments['plantilla'] = 'default';
        $options = array();
        $gxp = new psdfGenerateXpdlPackage();
        $xpdl = $gxp->execute($arguments, $options);
        $this->setXpdl($xpdl);

        return true;
    }

    public function generateXpdlProcess($procesoXpdlId, $procesoXpdlName, $procesoXpdlDisplayName) {

        if( !$this->xpdlLoad() ) {
            return false;
        }

        $xp = new domxpath( $this->xml );

        // Verifico ya no exista el proceso
        if( $this->xpdlProcessExist($procesoXpdlId) or
                $this->xpdlProcessExist($procesoXpdlName) ) {
            return false;
        }

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
        if($pos<=0) {
            $key = '</xpdl2:RedefinableHeader>';
            $contentPack = str_replace($key, $key.'<xpdl2:Pools></xpdl2:Pools>', $contentPack);
        }
        // Inserto al final de pools
        $key = '</xpdl2:Pools>';
        $contentPack = str_replace($key, $contentPool.$key, $contentPack);

        // Proceso
        // Defino etiqueta WorkflowProcesses si es el primero
        $pos = strpos($contentPack, '<xpdl2:WorkflowProcesses>');
        if($pos<=0) {
            $key = '<xpdl2:ExtendedAttributes>';
            $contentPack = str_replace($key, '<xpdl2:WorkflowProcesses></xpdl2:WorkflowProcesses>'.$key, $contentPack);
        }
        // Inserto al final de WorkflowProcesses
        $key = '</xpdl2:WorkflowProcesses>';
        $contentPack = str_replace($key, $contentProc.$key, $contentPack);

        $this->setXpdl($contentPack);
    }

    /**
     * Borra del xpdl el nodo proceso del parametro
     * Esta metodo debe llamarse cada vez que se de de baja un objeto proceso
     * @param $procesoId
     * @return unknown_type
     */
    public function deleteXpdlProcess($procesoXpdlId) {
        if( !$this->xpdlLoad() )
            throw new sfException(sprintf('No se pudo cargar xml/xpdl del paquete '.$this->getNombre()));

        $xp = new domxpath( $this->xml );
        $nodeList = $xp->query( "/xpdl2:Package/xpdl2:WorkflowProcesses/xpdl2:WorkflowProcess[ @Id=\"".$procesoXpdlId."\"]" );

        if( $nodeList->length==1 ) {
            $parent = $this->xml->documentElement->getElementsByTagName('WorkflowProcesses');
            $parent->item(0)->removeChild($nodeList->item(0));
        }
    }

    /**
     * Implementa el paquete como un modulo symfony
     *
     * @return unknown_type
     */
    public function implement() {  
        $arguments['application'] = $this->getMacro()->parseImplementationName();
        $arguments['module'] = $this->parseImplementationName();
        $options = array();
        $app = new psdfGeneratePackage();
        $app->execute($arguments, $options);
        unset($app);
    }

/**
* Construccion de procesos del paquete. 
* Puede hacerlo solamente para los especificados en el parametro o
* para todos los contenidos en el paquete si se omite.
* @param array $pProcessId  Lista de procesos a construir (opcional
* @return unknown_type
*/
public function build($ids=array())
{
    // Cargo la instancia xpdl
    if( !$this->xpdl ) {
        $this->loadXpdl();
    }

    // Si el Macro aún no fué implementado lo genero ahora
    if( !$this->getMacro()->isImplemented() ) {
        $this->getMacro()->implement();
    }
         
    // Si el Paquete aún no fué implementado, lo genero ahora
    if( !$this->isImplemented() ) {
      $this->implement();
    }    	 

    // Obtengo datafiels, participantes y tipos de datos del paquete
    $datafields = $this->xpdl->getDataFields();
    $participants = $this->xpdl->getParticipants();
    $type_declarations = $this->xpdl->getTypeDeclarations();

    // Obtengo procesos parametrizados o todos si es omitido
    if( count($ids)>0 ) {
        $procList = Doctrine::getTable('Proceso')->findByList($ids);
    }
    else {
        $procList = $this->getProcesos();
    }
      
    // Construyo el proceso
    foreach( $procList as $proceso ) {
        $this->buildProcess( $proceso, $datafields, $participants, $type_declarations );
    }
  }

    /**
    * Construccion del proceso
    *
    * @param $pMacroXpdlName     Name xpdl del Paquete Macro
    * @param $pPackXpdlName      Name xpdl del Paquete
    * @param $pDomXpdl           Dom xpdl del paquete
    * @param $pTypeDeclarations  Array con info de los tipos declarados del paquete
    * @param $pDataFields        Array con info de los datafields del paquete
    * @param $pParticipants      Array con info de participantes del paquete
    * @return unknown_type
    */
    public function buildProcess( $process, $datafields, $participants, $type_declarations )
    {
        // Obtengo datafields y participantes (concateno a los del paquete)
        $datafields = array_merge( $datafields, $this->xpdl->getDataFields($process->getXpdlId()) );
        $participants = array_merge( $participants, $this->xpdl->getParticipants($process->getXpdlId()) );

        // Parametros del proceso
        //$parameters = $this->xpdl->getParameters();

        // Recorro sus actividades
        $activities = $this->xpdl->getActivities($process->getXpdlId());
        foreach ( $activities as $activity ) {

            $scriptSetDF = '';
            $ptnName = '';
            $scriptPtnSetParams = '';
            $scriptPtnUrlTemplate = '';
            $scriptNA = '';

            // Informacion a anexar a los errores
            $info = sprintf(' [Info (paquete.proceso): %s.%s]', $this->getNombre(), $process->getNombre());

            if( $activity['type']=='StartEvent' ) {

    		// PERSONALIZAR ACTIVIDAD INICIO

    		// Genero script Inicializacion de datafields
    		foreach( $datafields as $key => $datafield ) {
                    // $this->f->setDataField('datafield', 'value');
                    $scriptSetDF.= "%sthis->f->setDataField('%s', '%s');%s";
                    $scriptSetDF = sprintf($scriptSetDF, chr(36), $key,$datafield["initialValue"], chr(10) );
    		}
            }
            elseif( $activity['type']=='EndEvent' ) {

                // PERSONALIZAR ACTIVIDAD FIN

            }
            elseif( $activity['type']=='TaskUser' or
                    $activity['type']=='TaskManual' or $activity['type']=='TaskScript' ) {

                // PERSONALIZAR ACTIVIDADES DE TIPOS YA IMPLEMENTADOS

                // Tratamiento de patrones
                $patterns = $this->xpdl->getPsdfPatterns($process->getXpdlId(), $activity['id']);

                // Regla momentanea de un solo patron
                if( count($patterns)>1 ) {
                    throw new sfException(sprintf('Aun no implementado la ejecucion de mas de un patron asociado a la actividad %s'.$info, $activity['name']));
                }

                foreach( $patterns as $key => $pattern ) {

                    // Valido exista el patron
                    if( !UtilPattern::exists($key)) {
                        throw new sfException(sprintf('No existe el patron "%s" asociado a la actividad %s'.$info, $key, $activity['name']));
                    }

                    // Instancio clase del patron
                    $cls = $key.'Pattern';
                    $ptn = new $cls;

                    $ptnName = $ptn->getName();

                    // Genero script de lectura de parametros a pasar al patron

                    foreach( $pattern['Params'] as $param => $value ) {
                        // Si es un array convierto a string vacio para omitir warnings
                        $v = is_array($value)?'':$value;
                        if( array_key_exists($v, $datafields) ) {
                            // Es un dataField: $ptn->setParameter( 'param', $this->f->getDataField('datafield') );
                            $scriptPtnSetParams.= "%sptn->setParameter( '%s', %sthis->f->getDataField('%s') );%s";
                            $scriptPtnSetParams = sprintf($scriptPtnSetParams, chr(36), $param, chr(36), $value, chr(10));
      			}
      			elseif( strpos($v, '%') and strrpos($v, '%') ) {
                            // Es una variable de entorno: $ptn->setParameter( 'param', $this->f->getContextVar('var') );
                            $scriptPtnSetParams.= "%sptn->setParameter( '%s', %sthis->f->getContextVar('%s') );%s";
                            $scriptPtnSetParams = sprintf($scriptPtnSetParams, chr(36), $param, chr(36), $value, chr(10));
     			}
                        elseif( is_array($value )) {
                            // Es un array: $ptn->setParameter( 'param', 'valor' );
                            $scriptPtnSetParams.= "%sptn->setParameter( '%s', '%s' );%s";
                            $scriptPtnSetParams = sprintf($scriptPtnSetParams, chr(36), $param, sfYaml::dump($value), chr(10));
                        }
      			else {
                            // Es un numerico/alfanumerico: $ptn->setParameter( 'param', 'valor' );
                            $scriptPtnSetParams.= "%sptn->setParameter( '%s', '%s' );%s";
                            $scriptPtnSetParams = sprintf($scriptPtnSetParams, chr(36), $param, $value, chr(10));
     			}
                    }

                    // Si el patron tiene interfaz paso a la accion/template los parametros

                    if( $ptn->hasTemplate() ) {
                        /* Omitido porque ahora los parametros se pasan con parseTemplateParams() en la accion
                        foreach( $pattern['Params'] as $param => $value ) {
                            // $this->param = $ptn->getParameter( 'param');
                            $scriptPtnSetTemplate.= "%sthis->%s = %sptn->getParameter( '%s' );%s";
                            $scriptPtnSetTemplate = sprintf($scriptPtnSetTemplate, chr(36), $param, chr(36), $param, chr(10));
                        } */
	          	// Ej: $this->include = 'urlTemplate';
                        //$scriptPtnSetTemplate.= "%sthis->include = '%s';%s";
                        //$scriptPtnSetTemplate = sprintf($scriptPtnSetTemplate, chr(36), $ptn->getTemplate(), chr(10));
                        $scriptPtnUrlTemplate = $ptn->getTemplate();
                    }
                }

                // Genero script de actualizacion de datafields por retorno del patron
                // Hoy UN SOLO patron
                foreach( $patterns as $key => $pattern ) {
                    foreach( $pattern['Params'] as $param => $value ) {
                        // Si es un array convierto a string vacio para omitir warnings
                        $v = is_array($value)?'':$value;
                        if( array_key_exists($v, $datafields) ) {
                            // Es un dataField: $this->f->setDataField( 'datafield', $ptn->getParameter( 'param') );
                            $scriptSetDF.= "%sthis->f->setDataField( '%s', %sptn->getParameter( '%s') );%s";
                            $scriptSetDF = sprintf($scriptSetDF, chr(36), $value, chr(36), $param, chr(10));
                        }
                    }
                }
            }
            else {
                throw new sfException(
                        sprintf('La Tarea %s bpmn es del tipo %s aun no implementada'.$info, $activity['name'], $activity['type']));
            }

            // Genero script de determinacion de siguiente actividad
            // Para eso recupero las posibles siguientes actividades
            $nextActivity = null;
            $nexts = $this->xpdl->getNextActivities($process->getXpdlId(), $activity['id']);

            if( count($nexts)>1 ) {
                // Aqui debo determinar mediante reglas por cual lado seguir
                throw new sfException(
                        sprintf('La Tarea %s tiene más de una transición, aún no implementada'.$info, $activity['name']));
            }
            if( count($nexts)==0 ) {
                if( $activity['type']!='EndEvent' ) {
                    // Aqui debo determinar mediante reglas por cual lado seguir
                    throw new sfException(
                            sprintf('La Tarea %s debe tener por lo menos una transicion hacia otra actividad'.$info, $activity['name']));
                }
            }
            if( count($nexts) == 1 ) {
                $nextActivity = $this->parseActivityImplementationName($process->getNombre(), $nexts[0]['name'], $nexts[0]['type']);
                // $next = 'nextActivity';
                $scriptNA = "%snext = '%s';%s";
                $scriptNA = sprintf($scriptNA, chr(36), $nextActivity, chr(10));
            }

            $scripts = array(
                'set_datafields' => $scriptSetDF,
                'ptn_name' => $ptnName,
                'ptn_set_params' => $scriptPtnSetParams,
                'ptn_url_template' => $scriptPtnUrlTemplate,
                'rules_next' => $scriptNA,
                );

            $arguments = array(
                'application' => $this->getMacro()->parseImplementationName(),
                'module' => $this->parseImplementationName(),
                'action' => $this->parseActivityImplementationName($process->getNombre(), $activity['name'], $activity['type']),
                'process' => array('id'=>$process->getId(), 'name'=>$process->getNombre()),
                'activity' => $activity,
                'scripts' => $scripts
            );

            $this->implementActivity( $arguments );
        }
    }


public function loadXpdl() {
    $this->xpdl = new Xpdl($this->getXpdl());
}
  
/**
* Valida si el paquete ya esta implementado.
* Esta validacion es directamente sobre el directorio.
* Un paquete se implementa como modulo symfony mediante el Name
* 
* @return boolean
*/
public function isImplemented() {
    $app = $this->getMacro()->parseImplementationName();
    $module = $this->parseImplementationName();

    $moduleDir = sfConfig::get('sf_apps_dir').'/'.$app.'/modules/'.$module;
    if (is_dir($moduleDir)) {
        return true;
    }
    return false;
}

    /**
     * Sincroniza los procesos del atributo xpdl con los objetos procesos.
     * Si no existe el objeto lo crea, si cambio por ej. el nombre lo actualiza
     * @param array $pIdXpdls Lista especifica de id (xpdl) de procesos a tratar,
     *                          sino procedo con todos.
     * @return array procesos no actualizados por algun error
     */
    public function syncProcess( $pIdXpdls=array()) {

        if( !$this->xpdl ) {
            $this->loadXpdl();
        }

        // Para ir volcando los paquetes no procesados por alguna regla invalida
        $noimp = array();


        // Obtengo informacion de procesos a sincronizar
        $procs = $this->xpdl->getProcessArray($pIdXpdls);

        // Procedo con cada uno
        foreach( $procs as $pr ) {

            $proc = Doctrine::getTable('Proceso')->findOneByXpdlId($pr['id']);

            // REGLAS
            
            $err = array();

            if( $proc ) {
                if( $proc->getId() != $pr['psdf_id'] ) {
                    $err['file'] = $this->getNombre(); // Uso el nombre del paquete
                    $err['error'] = 'Ya existe el Proceso '.$pr['name'].' pero no coinciden los ids';
                }
            }

            // CONTINUO SI SE PASARON TODAS LAS REGLAS

            if( count($err)==0 ) {

                if( !$proc ) {
                    $proc = new Proceso();
                    $proc->setNombre($pr['name']);
                    $proc->setRelPaquete($this->getId());
                    $proc->setXpdlId($pr['id']);
                    $proc->save();
                    $this->setPsdfProcessDataInXpdl($pr['id'], $proc->getId());
                }
                else {
                    if( $proc->getNombre()!=$pr['name'] ) {
                        $proc->setNombre($pr['name']);
                    }
                }
                if( $proc->isNew() or $proc->isModified() ) {
                    $proc->save();
                }
            }
            else {
                $noimp[] = $err;
            }
        }
        return $noimp;
    }

    public function setPsdfDataInXpdl($paquete, $macro, $macro_name) {
        if( !$this->xpdl ) {
            $this->loadXpdl();
        }

        $this->xpdl->setPsdfPaquete($paquete);
        $this->xpdl->setPsdfMacro($macro);
        $this->xpdl->setPsdfMacroName($macro_name);
        // Actualizo el atributo xpdl con los cambios
        $this->setXpdl( $this->xpdl->getContent() );
    }

    public function setPsdfProcessDataInXpdl($process_id, $proceso_id) {
        if( !$this->xpdl ) {
            $this->loadXpdl();
        }

        $this->xpdl->setPsdfProceso($process_id, $proceso_id);
        // Actualizo el atributo xpdl con los cambios
        $this->setXpdl( $this->xpdl->getContent() );

    }

    public function parseImplementationName() {
        $name = strtolower( str_replace(' ', '_', $this->getNombre()) );
        return $name;
    }

    /**
     * Recupera el nombre que tendrá la actividad al implementarse
     * Ahora lo hace concatenando adelante el nombre del proceso.
     * @param $nodeActivity
     * @return string action
     */
    public function parseActivityImplementationName($process_name, $act_name, $act_type) {
        // Fuerzo nombre de la accion con el nombre de proceso y actividad
        $action = $process_name;
        if( $act_type=='StartEvent' ) {
            $action.= '_Start';
        }
        elseif( $act_type=='EndEvent' ) {
            $action.= '_End';
        }
        else {
            $action.= '_'.$act_name;
        }

        $action = strtolower( str_replace(' ', '_', $action) );

        return $action;
    }

    /**
     * Construccion de una actividad de un proceso
     * @param $pMacroXpdlName       Name xpdl del Paquete Macro
     * @param $pPackXpdlName        Name xpdl del Paquete
     * @param $pActXpdlName         Name xpdl de la actividad
     * @param $pActType             Tipo de actividad (Tarea Bpmn)
     * @param $pScripts             Scripts Php personalizacion actividad
     * @return unknown_type
     */
    public function implementActivity( $pArguments ) {

        $options = array();
        $app = new psdfGenerateActivity();
        $app->execute($pArguments, $options);
    }
}