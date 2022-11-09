<?php
namespace Seolan\Core\Rocketchat;

//Cette classe permet de faire la communication en la console et le serveur rocketchat

class Rocketchat{
    // token d'un utilisateur avec les droits admin pour faire certaines requête
    protected $admin_token;
    // identifiant Rocketchat de l'utilisateur admin
    protected $admin_id;
    //url de base pour construire une requête API
    protected $api;
    //url du serveur ou est installé rocketchat
    public $server;

    protected $rq_id;
    //Constante qui défini le groupe des super-utilisateurs (toute les personnes de ce groupe sont les seule à pouvoir écrire dans les chats d'équipes)
    protected $Moderator = "GRP:1";//GRP:1 => Administrateurs
    //Constante qui permet de définir la limite d'affichage avant d'utiliser la notation +
    protected $MaxUnreadMessage = 9;// => si 10 messages non lus ou plus -> on affiche 9+


    function __construct($ar){
        if($ar['admin_token']){
            $this->admin_token = $ar['admin_token'];
        } else {
            $this->admin_token = TZR_ADMIN_ROCKETCHAT_TOKEN;
        }
        if($ar['admin_id']){
            $this->admin_id = $ar['admin_id'];
        } else {
            $this->admin_id = TZR_ADMIN_ROCKETCHAT_ID;
        }
        if($ar['api']){
            $this->api = $ar['api'];
        } else {
            $this->api = TZR_ROCKETCHAT_API;
        }
        if($ar['server']){
            $this->server = $ar['server'];
        } else {
            $this->server = TZR_ROCKETCHAT_SERVEUR_URL;
        }
        if($ar['rq_id']){
            $this->rq_id = $ar['rq_id'];
        } else {
            $this->rq_id = TZR_ROCKETCHAT_RQ_ID;
        }
    }

    /**
       Permet de récupérer tout les utilisateurs présent sur Rocketchat
     */
    function getAllUserId(){
        $uids = [];
        $header = ["X-Auth-Token: ".$this->admin_token,"X-User-Id: ".$this->admin_id];
        $rq = new \Seolan\Core\JsonClient\Request($header);
        $url = $this->api."users.list?count=10000";
        $response = json_decode($rq->doRequest('GET',$url,[],[])->data,true);
        foreach($response["users"] as $u){
            $uids[] = $u["_id"];
        }
        return $uids;
    }

    /// Supprime tous les utilisateurs (sauf api et rq) et tout les messages de la base
    function cleanAll(){
        //On récupère les id de tous les utilisateurs
        $userIds = $this->getAllUserId();
        foreach($userIds as $uid){
            // On fais attention de ne pas supprimer l'admin
            if($uid != $this->admin_id && $uid !=$this->rq_id){
                $header = ["X-Auth-Token: ".$this->admin_token,"X-User-Id: ".$this->admin_id,"Content-Type: application/json"];
                $rq = new \Seolan\Core\JsonClient\Request($header);
                $url = $this->api."users.delete";
                $param = ["userId"=>$uid];
                $response = json_decode($rq->doRequest('POST',$url,$param,[])->data,true);
            }
        }
    }

    /**
       Permet de récupérer tout les utilisateurs du site présent sur Rocketchat
     */
    function getCurrentSiteUserId(){
        $uids = [];
        $header = ["X-Auth-Token: ".$this->admin_token,"X-User-Id: ".$this->admin_id];
        $rq = new \Seolan\Core\JsonClient\Request($header);
        $url = $this->api."users.list?count=10000";
        $response = json_decode($rq->doRequest('GET',$url,[],[])->data,true);
        foreach($response["users"] as $u){
            preg_match('('.rewriteToAscii(\Seolan\Core\Ini::get('societe_url'),false,false).')', $u["emails"][0]["address"], $matches, PREG_OFFSET_CAPTURE);
            if($matches){
                $uids[] = $u["_id"];
            }
        }
        return $uids;
    }

    /// Supprime tout les utilisateurs rocketchat du site
    function cleanCurrentSiteUser(){
        $uids = $this->getCurrentSiteUserId();
        foreach($uids as $uid){
            // On fais attention de ne pas supprimer les admins
            if($uid != $this->admin_id && $uid !=$this->rq_id){
                $header = ["X-Auth-Token: ".$this->admin_token,"X-User-Id: ".$this->admin_id,"Content-Type: application/json"];
                $rq = new \Seolan\Core\JsonClient\Request($header);
                $url = $this->api."users.delete";
                $param = ["userId"=>$uid];
                $response = json_decode($rq->doRequest('POST',$url,$param,[])->data,true);
            }
        }
    }

    /**
       Permet de mettre à jour l'avatar du compte rocketchat
     */
    function syncAvatar(string $koid, string $avatar){
        $admin_id = $this->admin_id;
        $admin_token = $this->admin_token;
        $header = ["X-Auth-Token: ".$admin_token,"X-User-Id: ".$admin_id,"Content-Type: application/json"];
        $rq = new \Seolan\Core\JsonClient\Request($header);
        
        $url = $this->api."users.setAvatar";
        $uid = $this->getUserId($koid);
        $param = ["avatarUrl"=>$avatar,"userId"=>$uid];
        $response = json_decode($rq->doRequest('POST',$url,$param,[])->data,true);
        if(!$response["success"]){
            $msg .= "une erreur c'est produite lors de la mise à jour de l'avatar de l'utilisateur '".$koid."' (".$username.") :".$response["error"];
        }
    }

    /// Cette fonction permet de synchroniser les données d'un utilisateur de la console avec Rocketchat
    function synchroUser(string $koid, string $email, string $name, string $avatar, $groups){
        $msg = "";
        $RCusername = $this->getRocketchatUsername($koid,["name"=>$name]);
        $username = $RCusername[0];
        $compteur = $RCusername[1];
        $email = $this->getRocketchatEmail($koid,$email);
        $admin_id = $this->admin_id;
        $admin_token = $this->admin_token;
        $uid = $this->getUserId($koid);
        $customFields = ["koid"=>$koid,"compteur"=>$compteur];
        $this->generateToken($koid);
        
        $data = [
            "name"=>$name,
            "email"=>$email,
            "username"=>$username,
            "customFields"=>$customFields,
        ];
        if($data["email"]){
            $data["verified"] = true;
        }
        $header = ["X-Auth-Token: ".$admin_token,"X-User-Id: ".$admin_id,"Content-Type: application/json"];
        $rq = new \Seolan\Core\JsonClient\Request($header);
        $url = $this->api."users.update";
        $param = ["userId"=>$uid,"data"=>$data];
        $response = json_decode($rq->doRequest('POST',$url,$param,[])->data,true);
        if(!$response["success"]){
            $msg .= "Une erreur c'est produite lors de la mise à jour de l'utilisateur '".$koid." (".$username.")' : ".$response["error"];
        }
        if(strlen($avatar) > 0){
            //Création de l'avatar
            $this->syncAvatar($koid,$avatar);
        }
        $changed .= "no change";
        if(!empty($groups)){
            $changed = $this->checkGroups($koid,$groups);
        }
        return ["msg"=>$msg,"changed"=>$changed];
    }
    
    ///Permet de récupérer le token dans les cookies ou dans générer un nouveau si besoin
    function getUserToken(string $koid){
        $varName = "RocketchatToken";
        $token = getDB()->fetchOne("select value from _VARS where name=? and user=?",[$varName,$koid]);
        if(empty($token)){
            $this->generateToken($koid);
        }
        return $token;
    }

    ///Permet de récupérer l'id du compte rocketchat de l'utilisateur connecté
    function getUserId(string $koid){
        $mail = $this->getRocketchatEmail($koid);
        $admin_token = $this->admin_token;
        $admin_id = $this->admin_id;
        $header = ["X-Auth-Token: ".$admin_token,"X-User-Id: ".$admin_id];
        $rq = new \Seolan\Core\JsonClient\Request($header);
        $url = $this->api.'users.list?count=10&fields={"name":0,"roles":0,"status":0,"active":0,"emails":0}&query={"emails.address":{"$eq":"'.urlencode($mail).'"}}';
        $uid = json_decode($rq->doRequest('GET',$url,[],[])->data,true);
        if(empty($uid["users"]))
            return "no id";
        return $uid["users"][0]["_id"];
    }

    /**
    Infos est un tableau qui contient les différents champs que l'on souhaite récupérer
    Valeur possible dans infos : _id, username, emails=>address, status, roles, name, customFields=>koid
    */
    function getUserInfosFromId(string $id, $infos){
        $admin_token = $this->admin_token;
        $admin_id = $this->admin_id;
        $header = ["X-Auth-Token: ".$admin_token,"X-User-Id: ".$admin_id];
        $rq = new \Seolan\Core\JsonClient\Request($header);
        $url = $this->api.'users.list?count=10&fields={"customFields":1}&query={"_id":{"$eq":"'.urlencode($id).'"}}';
        $response = json_decode($rq->doRequest('GET',$url,[],[])->data,true)["users"][0];
        $result = [];
        foreach($infos as $k => $i){
            if(is_int($k)){
                $result[$i] = $response[$i];
            } else {
                $result[$i] = $response[$k][$i];
            }
        }
        return $result;
    }
    /**
       Permet de contrôler que la conversation rid existe
    */
    function checkRoomExist(string $rid, string $koid){
        $token = $this->getUserToken($koid);
        $uid = $this->getUserId($koid);
        $header = ["X-Auth-Token: ".$token,"X-User-Id: ".$uid];
        $rq = new \Seolan\Core\JsonClient\Request($header);
        $url = $this->api.'rooms.info?roomId='.urlencode($rid);
        $response = json_decode($rq->doRequest('GET',$url,[],[])->data,true)["success"];
        if($response){
            return $this->server.'/direct/'.$rid;
        } else {
            return false;
        }
    }
    
    ///Permet de changer le status de l'utilisateur courant à $target
    function switchStatus(string $status, string $koid){
        $token = $this->getUserToken($koid);
        $idu = $this->getUserId($koid);
        $header = ["X-Auth-Token: ".$token,"X-User-Id: ".$idu,"Content-Type: application/json"];
        $rq = new \Seolan\Core\JsonClient\Request($header);
        $url = $this->api."users.setStatus";
        $param = ["message"=>"","status"=>$status];
        $response = json_decode($rq->doRequest('POST',$url,$param,[])->data,true);
        $return = ["url"=>$url,"header"=>$header,"param"=>$param,"response"=>$response];
        return $return;
    }

    ///Permet de récupérer toute les équipes présentes sur le serveur rocketchat
    function getAllTeams(){
        $admin_token = $this->admin_token;
        $admin_id = $this->admin_id;
        $header = ["X-Auth-Token: ".$admin_token,"X-User-Id: ".$admin_id];
        $rq = new \Seolan\Core\JsonClient\Request($header);
        $url = $this->api."teams.listAll";
        $param = [];
        $response = json_decode($rq->doRequest('GET',$url,$param,[])->data,true);
        $allRocketGroups = [];
        foreach($response['teams'] as $t){
            $allRocketGroups[] = $t['name'];
        }
        return $allRocketGroups;
    }

    ///Permet de récupérer les groupes rocketchat de l'utilisateur
    function getUserGroups(string $koid){
        $idu = $this->getUserId($koid);
        $token = $this->getUserToken($koid);
        $rocketGroups = [];
        $header = ["X-Auth-Token: ".$token,"X-User-Id: ".$idu];
        $rq = new \Seolan\Core\JsonClient\Request($header);
        $url = $this->api."teams.list";
        $param = [];
        $response = json_decode($rq->doRequest('GET',$url,$param,[])->data,true);
        if(!$response["success"]){
            var_dump(["header"=>$header,"url"=>$url,"param"=>$param,"response"=>$response,"autres"=>["koid"=>$koid,"token"=>$token]]);
        }
        foreach($response['teams'] as $t){
            $rocketGroups[] = $t['name'];
        }
        return $rocketGroups;
    }

    ///Ajoute/Enlève l'utilisateur à des équipes sur RocketChat par rapport à ses groupes Seolan (et les créer si besoin)
    function checkGroups(string $koid, $groupList=NULL){
        if(empty($groupList)){
            $ds_user = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8("SPECS=USERS");
            $groupOids = $ds_user->display(['oid'=>$koid,'selectedfields'=>["GRP"],'tplentry'=>TZR_RETURN_DATA])['oGRP']->oidcollection;
            $groupList = [];
            foreach($groupOids as $gp){
                $groupList[] = getDB()->fetchCol("select GRP from GRP where KOID=?",[$gp])[0];
            }
        }
        if(\Seolan\Core\Ini::get('is_prod') == 0){
            $groupList = ["DEV"];
        }
        $idu = $this->getUserId($koid);
        //On récupère les groupes de l'utilisateur sur la console
        $groups = [];
        foreach($groupList as $gp){
            $groups[] = rewriteToAscii($gp,false,false);
        }
        //On récupère les groupes existant sur Rocket.Chat
        $allRocketGroups = $this->getAllTeams();
        //On récupère les groupes de l'utilisateur sur rocketchat
        $rocketGroups = $this->getUserGroups($koid);
        //on construit la liste des groupes à ajouter et à retirer
        $addList = array_diff($groups,$rocketGroups);
        $removeList = array_diff($rocketGroups,$groups);
        if(empty($addList) and empty($removeList)){
            return "no change";
        }
        $return = [];
        //On ajoute les groupes qu'il faut puis on enlève les groupes à enlever
        foreach($addList as $gp){
            $return[] = ["group"=>$gp,"idu"=>$idu];
            $this->addToTeam($gp,$idu,$allRocketGroups);
        }
        foreach($removeList as $gp){
            $return[] = ["group"=>$gp,"idu"=>$idu];
            $this->removeFromTeam($gp,$idu);
        }
        return $return;
    }

    ///Permet d'ajouter l'utilisateur avec l'identitfiant rocketchat idu dans le groupe gp
    function addToTeam(string $gp, string $idu, $allRocketGroups){
        $admin_token = $this->admin_token;
        $admin_id = $this->admin_id;
        $header = ["X-Auth-Token: ".$admin_token,"X-User-Id: ".$admin_id];
        $rq = new \Seolan\Core\JsonClient\Request($header);
        if(in_array($gp,$allRocketGroups)){
            //On ajoute l'utilisateur à l'équipe
            $url = $this->api."teams.addMembers";
            $param = ["teamName"=>$gp,"members"=>[["userId"=>$idu,"roles"=>["member"]]]];
            $response = json_decode($rq->doRequest('POST',$url,$param,["Content-Type: application/json"])->data,true);
        } else {
            //On créer le groupe
            $url = $this->api."teams.create";
            $param = ["name"=>$gp,"type"=>0,"members"=>[$idu],"room"=>["readOnly"=>true]];
            $response = json_decode($rq->doRequest('POST',$url,$param,["Content-Type: application/json"])->data,true);
        }
        return $response;
    }

    ///Enlève l'utilisateur qui a pour identifiant rocketchat idu du groupe gp
    function removeFromTeam(string $gp, string $idu){
        $admin_token = $this->admin_token;
        $admin_id = $this->admin_id;
        $header = ["X-Auth-Token: ".$admin_token,"X-User-Id: ".$admin_id,"Content-Type: application/json"];
        $rq = new \Seolan\Core\JsonClient\Request($header);
        $url = $this->api."teams.removeMember";
        $param = ["teamName"=>$gp,"userId"=>$idu];
        $response = json_decode($rq->doRequest('POST',$url,$param,[])->data,true);
        return $response;
    }

    /// Vérifie que l'utilisateur console à un compte rocketchat
    function isUserExist(string $koid){
        $mail = $this->getRocketchatEmail($koid);
        $admin_token = $this->admin_token;
        $admin_id = $this->admin_id;
        $header = ["X-Auth-Token: ".$admin_token,"X-User-Id: ".$admin_id];
        $rq = new \Seolan\Core\JsonClient\Request($header);
        $url = $this->api.'users.list?count=10000&fields={"name":0,"roles":0,"status":0,"active":0,"emails":0}&query={"emails.address":{"$eq":"'.urlencode($mail).'"}}';
        $isExist = json_decode($rq->doRequest('GET',$url,[],[])->data,true)["count"];
        return $isExist;
    }

    /// Vérifie que l'utilisateur est connecté sur rocketchat
    function isUserOnline(string $koid){
        $admin_token = $this->admin_token;
        $admin_id = $this->admin_id;
        $idu = $this->getUserId($koid);
        $header = ["X-Auth-Token: ".$admin_token,"X-User-Id: ".$admin_id];
        $rq = new \Seolan\Core\JsonClient\Request($header);
        $url = $this->api.'users.info';
        $param = ["userId"=>$idu];
        $isOnline = json_decode($rq->doRequest('GET',$url,$param,[])->data,true)["user"]["status"] == "online";
        return $isOnline;
    }

    /**
       Permet de récupérer l'email utilisé sur rocketchat
    */
    function getRocketchatEmail(string $koid, string $email=""){
        if(empty($email)){
            $ds_user = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8("SPECS=USERS");
            $email = trim($ds_user->display(["tplentry"=>TZR_RETURN_DATA,"oid"=>$koid,"selectedfields"=>["alias"]])["oalias"]->raw);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $email .= "@rocketchat.fr";
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return "Pb : impossible de récupérer un email valide pour l'utilisateur ".$koid;
                }
            }
        }
        $mail = rewriteToAscii(\Seolan\Core\Ini::get('societe_url'),false,false).$email;
        return $mail;
    }

    /**
       Permet de récupérer le username de l'utilisateur sur Rocketchat
     */
    function getRocketchatUsername(string $koid, $params=[]){
        // On regarde si un compte existe avec l'adresse mail
        $admin_token = $this->admin_token;
        $admin_id = $this->admin_id;
        $header = ["X-Auth-Token: ".$admin_token,"X-User-Id: ".$admin_id];
        $rq = new \Seolan\Core\JsonClient\Request($header);
        
        $mail = $this->getRocketchatEmail($koid);
        // On récupére le nom/prénom de l'utilisateur
        $name = "";
        if($params["name"]){
            $name = $params["name"];
        } else {
            $ds_user = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8("SPECS=USERS");
            $user = $ds_user->display(["tplentry"=>TZR_RETURN_DATA,"oid"=>$koid,"selectedfields"=>["fullnam"]]);
            $name = $user["ofullnam"]->raw;
        }
        $name = explode(" ",$name);
        // On créer le username avec les infos qu'on a
        $username = "";
        foreach($name as $n){
            $username .= ucfirst(strtolower($n));
        }
         
        if(empty($username)){
            die(json_encode(["message"=>"une erreur est survenu pendant la récupération du username : Username vide","error"=>["koid"=>$koid]]));
        }
        $username = rewriteToAscii($username,false,false);
        
        $url = $this->api.'users.list?count=10000&fields={"customFields":1}&query={"username":{"$regex":"'.urlencode($username).'[0-9]*$","$options":"i"}}';
        $response = json_decode($rq->doRequest('GET',$url,[],[])->data,true);
        if($response["success"]){
            $compteur = 1;
            foreach($response["users"] as $u){
                if($u["emails"][0]["address"] == $mail) return [$u["username"],$u["customFields"]["compteur"]];
                if($compteur <= $u["customFields"]["compteur"]) $compteur = $u["customFields"]["compteur"]+1;
            }
            if($compteur > 1){
                return [$username.$compteur,$compteur];
            } else {
                return [$username,1];
            }
        } else {
            die(json_encode(["message"=>"une erreur est arrivé pendant la récupération du compteur","response"=>$response]));
        }
    }

    ///Permet de vérifier que l'utilisateur à bien un compte rocketchat et de le créer si ce n'est pas le cas
    function checkUser(string $koid, $userInfos=NULL, $logo_url=NULL){
        if(empty($userInfos)){
            $ds_user = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8("SPECS=USERS");
            $user = $ds_user->display(['oid'=>$koid,'selectedfields'=>["fullnam","email","alias","logo","avatar","GRP"],'tplentry'=>TZR_RETURN_DATA]);
            $logo_url = $user['ologo']->resizer;
            $email = $this->getRocketchatEmail($koid);
            $rcUsername = $this->getRocketchatUsername($koid);
            $username = $rcUsername[0];
            $compteur = $rcUsername[1];
            $user = ["name"=>$user['ofullnam']->raw,"email"=>$email,"username"=>$username,"compteur"=>$compteur,"GRP"=>$user["oGRP"]->oidcollection];
        } else {
            $user = $userInfos;
        }
        if(in_array($this->Moderator,$user["GRP"])){
            $roles = [TZR_ROCKETCHAT_SEOLAN_GROUP,TZR_ROCKETCHAT_SEOLAN_MODERATOR_GROUP];
        } else {
            $roles = [TZR_ROCKETCHAT_SEOLAN_GROUP];
        }    
        if($logo_url){
            $user["avatar"] = \Seolan\Core\Ini::get('societe_url').$logo_url;
        }
        if(!strlen($user["name"])){
            $user["name"] = $user["username"];
        }
        $created = false;
        if($this->isUserExist($koid) == 0){
            $created = true;
            //l'utilisateur n'existe pas encore donc il faut le créer et créer ses groupes
            $admin_id = $this->admin_id;
            $admin_token = $this->admin_token;
            $password = TZR_DEFAULT_ROCKETCHAT_PWD;
            $header = ["X-Auth-Token: ".$admin_token,"X-User-Id: ".$admin_id,"Content-Type: application/json"];
            $rq = new \Seolan\Core\JsonClient\Request($header);
            $url = $this->api."users.create";
            $param = ["name"=>$user['name'],"email"=>$user['email'],"password"=>$password,"username"=>$user['username'],"roles"=>$roles,"verified"=>true,"joinDefaultChannels"=>false,/*"requirePasswordChange"=>true,*/"customFields"=>["koid"=>$koid,"compteur"=>$user["compteur"]]];
            $response = json_decode($rq->doRequest('POST',$url,$param,[])->data,true);
            if($response['success']){
                $idu = $response['user']['_id'];
                if(strlen($user['avatar']) > 0){
                    //Création de l'avatar
                    $this->syncAvatar($koid,$user['avatar']);
                }
            } else {
                $error = ["message"=>"un problème est survenu lors de la création de l'utilisateur","debug"=>$response,"user"=>$koid];
                var_dump($error);
            }
        }
        $token = $this->generateToken($koid);
        if($created){
            // On l'ajoute à ses groupes
            $this->checkGroups($koid);
        }
        return ["token"=>$token,"create"=>$created];
    }

    ///Permet d'enregistrer le token en base
    function saveToken(string $token, string $koid){
        $varName = "RocketchatToken";
        $isSave = getDB()->fetchOne("select COUNT(value) from _VARS where name=? and user=?",[$varName,$koid]);
        if($isSave){
            getDB()->execute("update _VARS set value=? where name=? and user=?",[$token,$varName,$koid]);
        } else {
            getDB()->execute("insert into _VARS (user,name,value) values (?,?,?)",[$koid,$varName,$token]);
        }
    }

    ///Permet de générer un nouveau token
    function generateToken(string $koid){
        $admin_id = $this->admin_id;
        $admin_token = $this->admin_token;
        $uid = $this->getUserId($koid);
        $header = ["X-Auth-Token: ".$admin_token,"X-User-Id: ".$admin_id,"Content-Type: application/json"];
        $rq = new \Seolan\Core\JsonClient\Request($header);
        $url = $this->api."users.createToken";
        $param = ["userId"=>$uid];
        $response = json_decode($rq->doRequest('POST',$url,$param,[])->data,true);
        if($response["success"]){
            $token = $response["data"]["authToken"];
            $this->saveToken($token,$koid);
            return $token;
        } else {
            $error = ["message"=>"Une erreur est survenu pendant la création du token","debug"=>$response,"autres"=>["header"=>$header,"param"=>$param,"url"=>$url,"koid"=>$koid]];
            die(var_dump($error));
        }
    }

    ///Permet de créer une conversation privé entre l'utilisateur koid et l'utilisateur targetkoid dont l'alias est username
    function createDM(string $username, string $koid, string $targetkoid){
        if($this->isUserExist($targetkoid) != 0){
            $uid = $this->getUserId($koid);
            $token = $this->getUserToken($koid);
            $header = ["X-Auth-Token: ".$token,"X-User-Id: ".$uid,"Content-Type: application/json"];
            $rq = new \Seolan\Core\JsonClient\Request($header);
            $url = $this->api."im.create";
            $param = ["username"=>$username];
            $response = json_decode($rq->doRequest('POST',$url,$param,[])->data,true);
            if($response["success"]){
                return ["url"=>$this->server.'/direct/'.$response["room"]["rid"]];
            } else {
                $error = ["message"=>"Une erreur est survenu pendant la création de la conversation","debug"=>$response,"autres"=>["header"=>$header,"param"=>$param,"url"=>$url,"koid"=>$koid]];
                return $error;
            }
        } else {
            return ["message"=>"l'utilisateur cible n'a pas de compte rocketchat"];
        }
    }

    /// Permet de vérifier que l'utilisateur a des messages non lus et combien
    function hasUnreadMsg(string $koid){
        $admin_id = $this->admin_id;
        $admin_token = $this->admin_token;
        $uid = $this->getUserId($koid);
        $header = ["X-Auth-Token: ".$admin_token,"X-User-Id: ".$admin_id];
        $rq = new \Seolan\Core\JsonClient\Request($header);
        $url = $this->api."users.info?userId=".$uid.'&fields={"userRooms":1}';
        $response = json_decode($rq->doRequest('GET',$url,[],[])->data,true);
        $rooms = $response['user']['rooms'];
        $unread = 0;
        foreach($rooms as $r){
            $unread += $r['unread'];
        }
        $ok = $unread > 0;
            
        if($unread > $this->MaxUnreadMessage){
            $unread = $this->MaxUnreadMessage."+";
        }
        return ["nb"=>$unread,"unread"=>$ok];
    }

    ///Permet de récupérer le koid de la personne à partir de son alias
    function getKoidFromAlias(string $alias){
        $admin_id = $this->admin_id;
        $admin_token = $this->admin_token;
        $header = ["X-Auth-Token: ".$admin_token,"X-User-Id: ".$admin_id];
        $rq = new \Seolan\Core\JsonClient\Request($header);
        $url = $this->api."users.info?username=".$alias;
        $koid = json_decode($rq->doRequest('GET',$url,[],[])->data,true)["user"]["customFields"]["koid"];
        return $koid;
    }

    ///Permet de récupérer le nom de la personne à partir de son alias
    function getNameFromAlias($alias){
        $admin_id = $this->admin_id;
        $admin_token = $this->admin_token;
        $header = ["X-Auth-Token: ".$admin_token,"X-User-Id: ".$admin_id];
        $rq = new \Seolan\Core\JsonClient\Request($header);
        $url = $this->api."users.info?username=".$alias;
        $name = json_decode($rq->doRequest('GET',$url,[],[])->data,true)["user"]["name"];
        return $name;
    }

    ///Permet de créer la liste des conversations qui ont au moins un message non lus à notifier à partir des conversations qui ont au moins un message non lus (unread) et des conversations qui ont été mise à jours depuis la dernière fois
    function mergeConv($unread, $update){
        $conv = [];
        foreach($unread as $urd){
            foreach($update as $upd){
                if($urd["rid"] == $upd["rid"]){
                    $name = $urd["name"];
                    $koid = $this->getKoidFromAlias($name);
                    if(in_array($name,$upd["usernames"])){
                        $name = $this->getNameFromAlias($name);
                    }
                    $conv[] = ["unread"=>$urd["unread"],"koid"=>$koid,"name"=>$name];
                }
            }
        }
        return $conv;
    }

    ///Permet de récupérer les conversations qui ont des messages non lus
    function getUnreadConv(string $koid){
        $admin_id = $this->admin_id;
        $admin_token = $this->admin_token;
        $uid = $this->getUserId($koid);
        $header = ["X-Auth-Token: ".$admin_token,"X-User-Id: ".$admin_id];
        $rq = new \Seolan\Core\JsonClient\Request($header);
        $url = $this->api."users.info?userId=".$uid.'&fields={"userRooms":1}';
        $response = json_decode($rq->doRequest('GET',$url,[],[])->data,true);
        $rooms = $response['user']['rooms'];
        $unread = [];
        foreach($rooms as $r){
            if($r["unread"] > 0){
                $unread[] = ["rid"=>$r["rid"],"unread"=>$r['unread'],"name"=>$r["name"]];
            }
        }
        return $unread;
    }

    ///Permet de récupérer les conversations mise à jours depuis la dernière fois
    function getUpdateConv(string $koid, string $lastSynchro){
        $uid = $this->getUserId($koid);
        $token = $this->getUserToken($koid);
        $header = ["X-Auth-Token: ".$token,"X-User-Id: ".$uid];
        $rq = new \Seolan\Core\JsonClient\Request($header);
        $url = $this->api."rooms.get?updatedSince=".$lastSynchro;
        $response = json_decode($rq->doRequest('GET',$url,[],[])->data,true);
        $rooms = $response['update'];
        $update = [];
        foreach($rooms as $r){
            if($r["lm"] > $lastSynchro){
                $update[] = ["rid"=>$r["_id"],"usernames"=>$r["usernames"],"lm"=>$r["lm"]];
            }
        }
        return $update;
    }

    ///Permet de récupérer les conversations à notifier
    function getUnread($koid,$lastSynchro){
        //On récupère les conversations avec au moins un message non lus (rid,unread,name)
        $unread = $this->getUnreadConv($koid);
        //On récupère les conversations qui ont une UPD > lastSynchro (_id,usernames)
        $update = $this->getUpdateConv($koid,$lastSynchro);
        $conv = $this->mergeConv($unread,$update);
        return $conv;
    }
}
