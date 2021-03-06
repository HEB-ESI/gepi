<?php
/*
*
* Copyright 2001, 2016 Thomas Belliard, Laurent Delineau, Edouard Hue, Eric Lebrun, Laurent Viénot-Hauger, Stephane Boireau
*
* This file is part of GEPI.
*
* GEPI is free software; you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
*
* GEPI is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with GEPI; if not, write to the Free Software
* Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

// On indique qu'il faut creer des variables non protégées (voir fonction cree_variables_non_protegees())
$variables_non_protegees = 'yes';

// Initialisations files
require_once("../lib/initialisations.inc.php");

// Resume session
$resultat_session = $session_gepi->security_check();
if ($resultat_session == 'c') {
	header("Location: ../utilisateurs/mon_compte.php?change_mdp=yes");
	die();
} else if ($resultat_session == '0') {
	header("Location: ../logout.php?auto=1");
	die();
}

if (!checkAccess()) {
	header("Location: ../logout.php?auto=1");
	die();
}

// Si le témoin temoin_check_srv() doit être affiché, on l'affichera dans la page à côté de Enregistrer.
$aff_temoin_serveur_hors_entete="y";

// initialisation
$id_classe = isset($_POST["id_classe"]) ? $_POST["id_classe"] :(isset($_GET["id_classe"]) ? $_GET["id_classe"] :NULL);

if((!isset($id_classe))||(!preg_match("/^[0-9]{1,}$/", $id_classe))) {
	header("Location: ../accueil.php?msg=Classe non choisie.");
	die();
}

include "../lib/periodes.inc.php";

$msg="";

//debug_var();


// On teste si un professeur peut saisir les avis
if (($_SESSION['statut'] == 'professeur') and getSettingValue("GepiRubConseilProf")!='yes') {
	die("Droits insuffisants pour effectuer cette opération");
}

// On teste si le service scolarité peut saisir les avis
if (($_SESSION['statut'] == 'scolarite') and getSettingValue("GepiRubConseilScol")!='yes') {
	die("Droits insuffisants pour effectuer cette opération");
}

// On teste si le service cpe peut saisir les avis
if (($_SESSION['statut'] == 'cpe') and getSettingValue("GepiRubConseilCpe")!='yes' and getSettingValue("GepiRubConseilCpeTous")!='yes') {
   die("Droits insuffisants pour effectuer cette opération");
}

if(($_SESSION['statut']=='professeur')&&(!is_pp($_SESSION['login'], $id_classe))) {
	header("Location: ../accueil.php?msg=Accès non autorisé.");
	die();
}

$gepi_prof_suivi=retourne_denomination_pp($id_classe);

$gepi_denom_mention=getSettingValue("gepi_denom_mention");
if($gepi_denom_mention=="") {
	$gepi_denom_mention="mention";
}

$mod_disc_terme_avertissement_fin_periode=getSettingValue('mod_disc_terme_avertissement_fin_periode');
if($mod_disc_terme_avertissement_fin_periode=="") {$mod_disc_terme_avertissement_fin_periode="avertissement de fin de période";}

$date_du_jour=strftime("%d/%m/%Y");
$acces_app_ele_resp=getSettingValue('acces_app_ele_resp');
//$acces_classes_acces_appreciations=acces("/classes/acces_appreciations.php", $_SESSION['statut']);
$acces_classes_acces_appreciations=false;
if(($_SESSION['statut']=='administrateur')||
($_SESSION['statut']=='scolarite')||
(($_SESSION['statut']=='professeur')&&(getSettingAOui('GepiAccesRestrAccesAppProfP'))&&(is_pp($_SESSION['login'], $id_classe)))) {
	$acces_classes_acces_appreciations=true;
}

//echo "\$acces_classes_acces_appreciations=$acces_classes_acces_appreciations<br />";
if((isset($id_classe))&&(isset($_GET['periode_num']))&&(isset($_GET['mode']))&&($_GET['mode']=='modifier_visibilite_parents')&&($acces_classes_acces_appreciations)) {
	check_token();
	$periode_num=$_GET['periode_num'];

	if($acces_app_ele_resp=='manuel') {
		$acces_app_classe=acces_appreciations($periode_num, $periode_num, $id_classe, 'responsable');
		//echo "\$acces_app_classe[$periode_num]=$acces_app_classe[$periode_num]<br />";
		if($acces_app_classe[$periode_num]=="y") {
			$sql="UPDATE matieres_appreciations_acces SET acces='n' WHERE id_classe='$id_classe' AND periode='$periode_num';";
			$msg="L'accès parent/élève n'est pas/plus ouvert pour la période n°$periode_num.<br />";
			$msg_no_js="<img src='../images/icons/invisible.png' width='19' height='16' alt='Appréciations non visibles des parents/élèves.' title=\"A la date du jour (".$date_du_jour."), les appréciations de la période ".$periode_num." ne sont pas visibles des parents/élèves.\" />";
		}
		else {
			$sql="UPDATE matieres_appreciations_acces SET acces='y' WHERE id_classe='$id_classe' AND periode='$periode_num';";
			$msg="L'accès parent/élève est maintenant ouvert pour la période n°$periode_num.<br />";
			$msg_no_js="<img src='../images/icons/visible.png' width='19' height='16' alt='Appréciations visibles des parents/élèves.' title='A la date du jour (".$date_du_jour."), les appréciations de la période ".$periode_num." sont visibles des parents/élèves.' />";
		}
		$res=mysqli_query($GLOBALS["mysqli"], $sql);
		if(!$res) {
			$msg="Erreur lors de la modification de la visibilité parent/élève.<br />";
			$msg_no_js="<img src='../images/icons/ico_attention.png' width='22' height='19' title='Erreur lors de la modification de la visibilité parent/élève.' alt='Erreur'>";
		}
	}
	elseif(($acces_app_ele_resp=='manuel_individuel')&&(isset($_GET['login_eleve']))) {
		$sql="SELECT * FROM matieres_appreciations_acces_eleve WHERE login='".$_GET['login_eleve']."' AND periode='".$periode_num."';";
		$test=mysqli_query($GLOBALS["mysqli"], $sql);
		if(mysqli_num_rows($test)==0) {
			$sql="INSERT INTO matieres_appreciations_acces_eleve SET acces='y', login='".$_GET['login_eleve']."', periode='$periode_num';";
			$msg="L'accès parent/élève est ouvert pour la période n°$periode_num pour cet élève.<br />";
			$msg_no_js="<img src='../images/icons/visible.png' width='19' height='16' alt='Visible' title=\"A la date du jour (".$date_du_jour."), les appréciations de la période ".$periode_num." sont visibles des parents/élèves pour cet élève.\" />";
		}
		else {
			$lig_acces_ele=mysqli_fetch_object($test);
			if($lig_acces_ele->acces=="y") {
				$sql="UPDATE matieres_appreciations_acces_eleve SET acces='n' WHERE login='".$_GET['login_eleve']."' AND periode='$periode_num';";
				$msg="L'accès parent/élève n'est pas/plus ouvert pour la période n°$periode_num pour cet élève.<br />";
				$msg_no_js="<img src='../images/icons/invisible.png' width='19' height='16' alt='Invisible' title=\"A la date du jour (".$date_du_jour."), les appréciations de la période ".$periode_num." ne sont pas visibles des parents/élèves pour cet élève.\" />";
			}
			else {
				$sql="UPDATE matieres_appreciations_acces_eleve SET acces='y' WHERE login='".$_GET['login_eleve']."' AND periode='$periode_num';";
				$msg="L'accès parent/élève est maintenant ouvert pour la période n°$periode_num pour cet élève.<br />";
				$msg_no_js="<img src='../images/icons/visible.png' width='19' height='16' alt='Visible' title='A la date du jour (".$date_du_jour."), les appréciations de la période ".$periode_num." sont visibles des parents/élèves pour cet élève.' />";
			}
		}
		//echo "$sql<br />";
		$res=mysqli_query($GLOBALS["mysqli"], $sql);
		if(!$res) {
			$msg="Erreur lors de la modification de la visibilité parent/élève.<br />";
			$msg_no_js="<img src='../images/icons/ico_attention.png' width='22' height='19' title='Erreur lors de la modification de la visibilité parent/élève.' alt='Erreur'>";
		}
	}
	else {
		$msg="L'accès ou non n'est pas modifié manuellement.<br />";
		$msg_no_js="<img src='../images/icons/ico_attention.png' width='22' height='19' title=\"L'accès ou non n'est pas modifié manuellement.\" alt='Erreur'>";
	}

	if(isset($_GET['mode_js'])) {
		echo $msg_no_js;
		die();
	}
}

if (isset($_POST['is_posted'])) {
	check_token();


	if(isset($_POST['enregistrer_ajout_a_textarea_vide'])) {
		if (isset($NON_PROTECT["ajout_a_textarea_vide"])) {
			$ajout_a_textarea_vide=traitement_magic_quotes(corriger_caracteres($NON_PROTECT["ajout_a_textarea_vide"]));
			$ajout_a_textarea_vide=preg_replace('/(\\\r\\\n){3,}+/',"\r\n",$ajout_a_textarea_vide);
			$ajout_a_textarea_vide=preg_replace('/(\\\r)+/',"\r",$ajout_a_textarea_vide);
			$ajout_a_textarea_vide=preg_replace('/(\\\n)+/',"\n",$ajout_a_textarea_vide);

			if(!saveSetting('default_mass_appreciation', $ajout_a_textarea_vide)) {
				$msg.="Erreur lors de l'enregistrement de 'default_mass_appreciation'<br />\n";
			}
		}
	}

	if (($_SESSION['statut'] == 'scolarite') or ($_SESSION['statut'] == 'secours') or (($_SESSION['statut'] == 'cpe')&&(getSettingAOui('GepiRubConseilCpeTous')))) {
		$sql="SELECT DISTINCT e.* FROM eleves e, j_eleves_classes c
		WHERE (c.id_classe='$id_classe' AND
		c.login = e.login
		) ORDER BY nom, prenom";
	}
	elseif(($_SESSION['statut'] == 'cpe')&&(getSettingAOui('GepiRubConseilCpe'))) {
		$sql="SELECT DISTINCT e.* FROM eleves e, j_eleves_classes jec, j_eleves_cpe jecpe
		WHERE (jec.id_classe='$id_classe' AND
		jec.login = e.login AND
		jecpe.e_login = jec.login AND
		jecpe.cpe_login = '".$_SESSION['login']."'
		) ORDER BY nom, prenom";
	} else {
		if(getSettingAOui('GepiAccesPPTousElevesDeLaClasse')) {
			if(!is_pp($_SESSION['login'], $id_classe)) {
				echo "<p style='color:red'>Vous n'êtes pas ".$gepi_prof_suivi." de $classe.</p>\n";
				require("../lib/footer.inc.php");
				die();
			}

			$sql="SELECT DISTINCT e.* FROM eleves e, j_eleves_classes c
			WHERE (c.id_classe='$id_classe' AND
			c.login = e.login
			) ORDER BY nom, prenom";
		}
		else {
			$sql="SELECT DISTINCT e.* FROM eleves e, j_eleves_classes c, j_eleves_professeurs p
			WHERE (c.id_classe='$id_classe' AND
			c.login = e.login AND
			p.login = c.login AND
			p.professeur = '".$_SESSION['login']."'
			) ORDER BY nom, prenom";
		}
	}
	//echo "$sql<br />";
	$quels_eleves = mysqli_query($GLOBALS["mysqli"], $sql);
	$lignes = mysqli_num_rows($quels_eleves);

	if($lignes>0) {
		// Synthèse
		$i = '1';
		while ($i < $nb_periode) {
			if ($ver_periode[$i] != "O"){
				if (isset($NON_PROTECT["synthese_".$i])){
					// On enregistre la synthese
					$synthese=traitement_magic_quotes(corriger_caracteres($NON_PROTECT["synthese_".$i]));
		
					//$synthese=my_ereg_replace('(\\\r\\\n)+',"\r\n",$synthese);
					$synthese=suppression_sauts_de_lignes_surnumeraires($synthese);

					$sql="SELECT 1=1 FROM synthese_app_classe WHERE id_classe='$id_classe' AND periode='$i';";
					$test=mysqli_query($GLOBALS["mysqli"], $sql);
					if(mysqli_num_rows($test)==0) {
						$sql="INSERT INTO synthese_app_classe SET id_classe='$id_classe', periode='$i', synthese='$synthese';";
						$insert=mysqli_query($GLOBALS["mysqli"], $sql);
						if(!$insert) {$msg.="Erreur lors de l'enregistrement de la synthèse.";}
						//else {$msg.="La synthèse a été enregistrée.";}
					}
					else {
						$sql="UPDATE synthese_app_classe SET synthese='$synthese' WHERE id_classe='$id_classe' AND periode='$i';";
						$update=mysqli_query($GLOBALS["mysqli"], $sql);
						if(!$update) {$msg.="Erreur lors de la mise à jour de la synthèse.";}
						//else {$msg.="La synthèse a été mise à jour.";}
					}
				}
			}
			$i++;
		}
	}

	$j = '0';
	$pb_record = 'no';
	while($j < $lignes) {
		$reg_eleve_login = old_mysql_result($quels_eleves, $j, "login");
		$i = '1';
		while ($i < $nb_periode) {
			if ($ver_periode[$i] != "O"){
				$call_eleve = mysqli_query($GLOBALS["mysqli"], "SELECT login FROM j_eleves_classes WHERE (login = '$reg_eleve_login' and id_classe='$id_classe' and periode='$i')");
				$result_test = mysqli_num_rows($call_eleve);
				if ($result_test != 0) {

					//=========================
					// AJOUT: boireaus 20071010
					unset($log_eleve);
					$log_eleve=$_POST['log_eleve_'.$i];

					// Récupération du numéro de l'élève dans les saisies:
					$num_eleve=-1;
					//for($k=0;$k<count($log_eleve);$k++){
					for($k=0;$k<$lignes;$k++){
						if(isset($log_eleve[$k])) {
							if("$reg_eleve_login"."_t".$i=="$log_eleve[$k]"){
								$num_eleve=$k;
								break;
							}
						}
					}
					if($num_eleve!=-1){
						//$nom_log = $reg_eleve_login."_t".$i;
						$nom_log = "avis_eleve_".$i."_".$num_eleve;
						//=========================
						// ***** AJOUT POUR LES MENTIONS *****
						unset($mention);
						$id_mention = isset($_POST['mention_eleve_'.$j.'_'.$i]) ? $_POST['mention_eleve_'.$j.'_'.$i] : NULL;
						// ***** FIN DE L'AJOUT POUR LES MENTIONS *****

						$avis = traitement_magic_quotes(corriger_caracteres($NON_PROTECT[$nom_log]));
						$avis=suppression_sauts_de_lignes_surnumeraires($avis);

						/*
						$test_eleve_avis_query = mysql_query("SELECT * FROM avis_conseil_classe WHERE (login='$reg_eleve_login' AND periode='$i')");
						$test = mysql_num_rows($test_eleve_avis_query);
						if ($test != "0") {
							$sql="UPDATE avis_conseil_classe SET avis='$avis',";
							if(isset($id_mention)) {$sql.="id_mention='$id_mention',";}
							$sql.="statut='' WHERE (login='$reg_eleve_login' AND periode='$i')";
							$register = mysql_query($sql);
						} else {
							$sql="INSERT INTO avis_conseil_classe SET login='$reg_eleve_login',periode='$i',avis='$avis',";
							if(isset($id_mention)) {$sql.="id_mention='$id_mention',";}
							$sql.="statut=''";
							$register = mysql_query($sql);
						}
						*/
						$sql="DELETE FROM avis_conseil_classe WHERE (login='$reg_eleve_login' AND periode='$i');";
						$menage=mysqli_query($GLOBALS["mysqli"], $sql);

						if(($avis!='')||((isset($id_mention)&&($id_mention!=0)))) {
							$sql="INSERT INTO avis_conseil_classe SET login='$reg_eleve_login',periode='$i',avis='$avis',";
							if(isset($id_mention)) {$sql.="id_mention='$id_mention',";}
							$sql.="statut=''";
							$register = mysqli_query($GLOBALS["mysqli"], $sql);

							if (!$register) {
								$msg.="Erreur lors de l'enregistrement des données de la période $i pour $reg_eleve_login<br />\n";
								$pb_record = 'yes';
							}
						}
					}
				}
			}

			$i++;
		}
		$j++;
	}
	if ($pb_record == 'no') $affiche_message = 'yes';
}


// 20160410
if((getSettingAOui('active_mod_orientation'))&&(mef_avec_proposition_orientation($id_classe))) {
	// Orientations type saisies dans la bases
	$tab_orientation=get_tab_orientations_types_par_mef();
	$tab_orientation2=get_tab_orientations_types();

	$tab_orientation_classe_courante=get_tab_voeux_orientations_classe($id_classe);

	$OrientationNbMaxVoeux=getSettingValue('OrientationNbMaxVoeux');
	$OrientationNbMaxOrientation=getSettingValue('OrientationNbMaxOrientation');
}

// 20160409: Orientation
$acces_saisie_voeux_orientation=acces_saisie_voeux_orientation($id_classe);
$acces_saisie_orientation=acces_saisie_orientation($id_classe);


$themessage = 'Des appréciations ont été modifiées. Voulez-vous vraiment quitter sans enregistrer ?';
$message_enregistrement = "Les modifications ont été enregistrées !";
$javascript_specifique = "saisie/scripts/js_saisie";
//**************** EN-TETE *****************
$titre_page = "Saisie des avis | Saisie";
require_once("../lib/header.inc.php");
//**************** FIN EN-TETE *****************
/*
echo "<pre>";
print_r($tab_orientation_classe_courante);
echo "</pre>";
*/
//=================================
$acces_bull_simp='n';
if(($_SESSION['statut']=="professeur") AND 
((getSettingValue("GepiAccesBulletinSimpleProf")=="yes")||
(getSettingValue("GepiAccesBulletinSimpleProfToutesClasses")=="yes")||
(getSettingValue("GepiAccesBulletinSimpleProfTousEleves")=="yes")
)) {
	$acces_bull_simp='y';
}
elseif($_SESSION['statut']=="scolarite") {
	$acces_bull_simp='y';
}
elseif($_SESSION['statut']=="cpe") {
	$acces_bull_simp='y';
}

$tmp_timeout=(getSettingValue("sessionMaxLength"))*60;

?>
<script type="text/javascript" language="javascript">
change = 'no';
</script>

<form enctype="multipart/form-data" action="saisie_avis1.php" name="form1" method='post'>
<p class='bold'><a href="saisie_avis.php" onclick="return confirm_abandon(this, change, '<?php echo $themessage; ?>')"><img src='../images/icons/back.png' alt='Retour' class='back_link'/> Mes classes</a>

<?php

// Ajout lien classe précédente / classe suivante
if($_SESSION['statut']=='scolarite') {
	$sql = "SELECT DISTINCT c.id,c.classe FROM classes c, periodes p, j_scol_classes jsc WHERE p.id_classe = c.id  AND jsc.id_classe=c.id AND jsc.login='".$_SESSION['login']."' ORDER BY classe";
}
elseif($_SESSION['statut']=='professeur') {

	// On a filtré plus haut les profs qui n'ont pas getSettingValue("GepiRubConseilProf")=='yes'
	$sql="SELECT DISTINCT c.id,c.classe FROM classes c,
										j_eleves_classes jec,
										j_eleves_professeurs jep
								WHERE jec.id_classe=c.id AND
										jep.login=jec.login AND
										jep.professeur='".$_SESSION['login']."'
								ORDER BY c.classe;";
}
elseif($_SESSION['statut']=='cpe') {
	// On ne devrait pas arriver ici en CPE...
	// Il n'y a pas de droit de saisie des avis du conseil.
	$sql="SELECT DISTINCT c.id,c.classe FROM classes c, periodes p, j_eleves_classes jec, j_eleves_cpe jecpe WHERE
		p.id_classe = c.id AND
		jec.id_classe=c.id AND
		jec.periode=p.num_periode AND
		jecpe.e_login=jec.login AND
		jecpe.cpe_login='".$_SESSION['login']."'
		ORDER BY classe";
}
elseif($_SESSION['statut'] == 'autre') {
	// On recherche toutes les classes pour ce statut qui n'est accessible que si l'admin a donné les bons droits
	$sql="SELECT DISTINCT c.* FROM classes c, periodes p WHERE p.id_classe = c.id  ORDER BY classe";
}
elseif($_SESSION['statut'] == 'secours') {
	$sql="SELECT DISTINCT c.* FROM classes c, periodes p WHERE p.id_classe = c.id  ORDER BY classe";
}

$chaine_options_classes="";

$cpt_classe=0;
$num_classe=-1;

$res_class_tmp=mysqli_query($GLOBALS["mysqli"], $sql);
$nb_classes_suivies=mysqli_num_rows($res_class_tmp);
if($nb_classes_suivies>0){
	$id_class_prec=0;
	$id_class_suiv=0;
	$temoin_tmp=0;
	while($lig_class_tmp=mysqli_fetch_object($res_class_tmp)){
		if($lig_class_tmp->id==$id_classe){
			// Index de la classe dans les <option>
			$num_classe=$cpt_classe;

			$chaine_options_classes.="<option value='$lig_class_tmp->id' selected='true'>$lig_class_tmp->classe</option>\n";
			$temoin_tmp=1;
			if($lig_class_tmp=mysqli_fetch_object($res_class_tmp)){
				$chaine_options_classes.="<option value='$lig_class_tmp->id'>$lig_class_tmp->classe</option>\n";
				$id_class_suiv=$lig_class_tmp->id;
			}
			else{
				$id_class_suiv=0;
			}
		}
		else {
			$chaine_options_classes.="<option value='$lig_class_tmp->id'>$lig_class_tmp->classe</option>\n";
		}
		if($temoin_tmp==0){
			$id_class_prec=$lig_class_tmp->id;
		}

		$cpt_classe++;

	}
}

// =================================
if(isset($id_class_prec)){
	if($id_class_prec!=0){echo " | <a href='".$_SERVER['PHP_SELF']."?id_classe=$id_class_prec' onclick=\"return confirm_abandon (this, change, '$themessage')\">Classe précédente</a>";}
}

if(($chaine_options_classes!="")&&($nb_classes_suivies>1)) {

	echo "<script type='text/javascript'>
	// Initialisation
	change='no';

	function confirm_changement_classe(thechange, themessage)
	{
		if (!(thechange)) thechange='no';
		if (thechange != 'yes') {
			document.form1.submit();
		}
		else{
			var is_confirmed = confirm(themessage);
			if(is_confirmed){
				document.form1.submit();
			}
			else{
				document.getElementById('id_classe').selectedIndex=$num_classe;
			}
		}
	}
</script>\n";

	//echo " | <select name='id_classe' onchange=\"document.forms['form1'].submit();\">\n";
	echo " | <select name='id_classe' id='id_classe' onchange=\"confirm_changement_classe(change, '$themessage');\">\n";
	echo $chaine_options_classes;
	echo "</select>\n";
}

if(isset($id_class_suiv)){
	if($id_class_suiv!=0){echo " | <a href='".$_SERVER['PHP_SELF']."?id_classe=$id_class_suiv' onclick=\"return confirm_abandon (this, change, '$themessage')\">Classe suivante</a>";}
}
//fin ajout lien classe précédente / classe suivante

if((acces('/impression/avis_pdf.php', $_SESSION['statut']))&&(acces('/saisie/impression_avis.php', $_SESSION['statut']))) {
	echo "| <a href='../saisie/impression_avis.php' onclick=\"return confirm_abandon(this, change, '$themessage')\">Impression PDF des avis</a>";
}

if((($_SESSION['statut']=='professeur')&&(getSettingAOui('CommentairesTypesPP')))||
(($_SESSION['statut']=='scolarite')&&(getSettingAOui('CommentairesTypesScol')))||
(($_SESSION['statut']=='professeur')&&(getSettingAOui('CommentairesTypesCpe')))) {
	echo " | <a href='commentaires_types.php' onclick=\"return confirm_abandon (this, change, '$themessage')\">Saisie de commentaires-types</a>";
}

if(isset($id_classe)) {
	$sql="SELECT num_periode FROM periodes WHERE id_classe='$id_classe' AND (verouiller='N' OR verouiller='P');";
	//echo "$sql<br />";
	$test_periode_ouverte=mysqli_query($GLOBALS["mysqli"], $sql);
	if(mysqli_num_rows($test_periode_ouverte)==1) {
		$lig_tmp_per=mysqli_fetch_object($test_periode_ouverte);
		echo " | <a href='saisie_avis2.php?id_classe=$id_classe&amp;periode_num=".$lig_tmp_per->num_periode."' onclick=\"return confirm_abandon (this, change, '$themessage')\">Saisie avis avec affichage bulletin simplifié</a>";
	}
}
echo "</p>\n";

echo "</form>\n";

// 20140226
if(getSettingAOui('active_mod_discipline')) {
	echo necessaire_saisie_avertissement_fin_periode();
}

// 20150318
if(getSettingValue('active_recherche_lapsus')!='n') {
	$tab_lapsus_et_correction=retourne_tableau_lapsus_et_correction();
}

// Pour que les signalements de faute et corrections du bulletin simplifié (affiché en infobulle) fonctionnent, il faut que les fonctions javascript soient définies dans la page de départ, pas dans celle appelée par ajax/js.
require_once("../lib/bulletin_simple.inc.php");
lib_signalement_fautes();
lib_corriger_appreciation();

echo "<form enctype='multipart/form-data' action='saisie_avis1.php' method='post'>\n";
echo add_token_field(true);

if ($id_classe) {
	$classe = sql_query1("SELECT classe FROM classes WHERE id = '$id_classe'");
	?>
	<p class= 'grand'>Avis du conseil de classe. Classe : <?php echo $classe; 
	echo " - <em style='font-size:small'>(".$gepi_prof_suivi."&nbsp;: ".liste_prof_suivi($id_classe, "profs", "y").")</em>";
	?>
	</p>
	<?php
	$test_periode_ouverte = 'no';
	$i = "1";
	while ($i < $nb_periode) {
		if ($ver_periode[$i] != "O") {
			$test_periode_ouverte = 'yes';
		}
		$i++;
	}
	?>
	<?php
	if (($_SESSION['statut'] == 'scolarite') or ($_SESSION['statut'] == 'secours') or (($_SESSION['statut'] == 'cpe')&&(getSettingValue("GepiRubConseilCpeTous")=='yes'))) {
		$sql="SELECT DISTINCT e.* FROM eleves e, j_eleves_classes c
		WHERE (c.id_classe='$id_classe' AND
		c.login = e.login
		) ORDER BY nom, prenom";
	} elseif ($_SESSION['statut'] == 'cpe') {
		$sql="SELECT DISTINCT e.* FROM eleves e, j_eleves_classes jec, j_eleves_cpe jecpe
		WHERE (jec.id_classe='$id_classe' AND
		jec.login = e.login AND
		jecpe.e_login = jec.login AND
		jecpe.cpe_login = '".$_SESSION['login']."'
		) ORDER BY nom, prenom";
	} else {
		if(getSettingAOui('GepiAccesPPTousElevesDeLaClasse')) {
			if(!is_pp($_SESSION['login'], $id_classe)) {
				echo "<p style='color:red'>Vous n'êtes pas ".$gepi_prof_suivi." de $classe.</p>\n";
				require("../lib/footer.inc.php");
				die();
			}

			$sql="SELECT DISTINCT e.* FROM eleves e, j_eleves_classes c
			WHERE (c.id_classe='$id_classe' AND
			c.login = e.login
			) ORDER BY nom, prenom";
		}
		else {
			$sql="SELECT DISTINCT e.* FROM eleves e, j_eleves_classes c, j_eleves_professeurs p
			WHERE (c.id_classe='$id_classe' AND
			c.login = e.login AND
			p.login = c.login AND
			p.professeur = '".$_SESSION['login']."'
			) ORDER BY nom, prenom";
		}
	}
	$appel_donnees_eleves=mysqli_query($GLOBALS["mysqli"], $sql);
	$nombre_lignes = mysqli_num_rows($appel_donnees_eleves);



/*
	CommentairesTypesPP
	CommentairesTypesScol
	CommentairesTypesCpe
*/

//=================================
// 20121118
// Si les parents ont accès aux bulletins ou graphes,... on va afficher un témoin
//$tab_acces_app_classe=array();
// L'accès est donné à la même date pour parents et responsables.
// On teste seulement pour les parents
$date_ouverture_acces_app_classe=array();
//$tab_acces_app_classe[$id_classe]=acces_appreciations(1, $nb_periode, $id_classe, 'responsable');

// 20160414
$tab_acces_app_classe2=array();
$tab_acces_app_classe2[$id_classe]=get_tab_acces_appreciations_ele(1, $nb_periode-1, $id_classe, 'responsable');

$acces_app_ele_resp=getSettingValue('acces_app_ele_resp');
if($acces_app_ele_resp=='manuel') {
	$msg_acces_app_ele_resp="Les appréciations seront visibles après une intervention manuelle d'un compte de statut 'scolarité'.";
}
elseif($acces_app_ele_resp=='manuel_individuel') {
	$msg_acces_app_ele_resp="Les appréciations seront visibles après une intervention manuelle d'un compte de statut 'scolarité'.";
}
elseif($acces_app_ele_resp=='date') {
	$chaine_date_ouverture_acces_app_classe="";
	for($loop=0;$loop<count($date_ouverture_acces_app_classe);$loop++) {
		if($loop>0) {
			$chaine_date_ouverture_acces_app_classe.=", ";
		}
		$chaine_date_ouverture_acces_app_classe.=$date_ouverture_acces_app_classe[$loop];
	}
	if($chaine_date_ouverture_acces_app_classe=="") {$chaine_date_ouverture_acces_app_classe="Aucune date n'est encore précisée.
Peut-être devriez-vous en poser la question à l'administration de l'établissement.";}
	$msg_acces_app_ele_resp="Les appréciations seront visibles soit à une date donnée (".$chaine_date_ouverture_acces_app_classe.").";
}
elseif($acces_app_ele_resp=='periode_close') {
	$delais_apres_cloture=getSettingValue('delais_apres_cloture');
	$msg_acces_app_ele_resp="Les appréciations seront visibles ".$delais_apres_cloture." jour(s) après la clôture de la période.";
}
else{
	$msg_acces_app_ele_resp="???";
}
//=================================

//===========================================================
echo "<div id='div_bull_simp' style='position: absolute; top: 220px; right: 20px; width: 700px; text-align:center; color: black; padding: 0px; border:1px solid black; display:none;'>\n";

	echo "<div class='infobulle_entete' style='color: #ffffff; cursor: move; width: 700px; font-weight: bold; padding: 0px;' onmousedown=\"dragStart(event, 'div_bull_simp')\">\n";
		echo "<div style='color: #ffffff; cursor: move; font-weight: bold; float:right; width: 16px; margin-right: 1px;'>\n";
		echo "<a href='#' onClick=\"cacher_div('div_bull_simp');return false;\">\n";
		echo "<img src='../images/icons/close16.png' width='16' height='16' alt='Fermer' />\n";
		echo "</a>\n";
		echo "</div>\n";

		echo "<div id='titre_entete_bull_simp'></div>\n";
	echo "</div>\n";
	
	echo "<div id='corps_bull_simp' class='infobulle_corps' style='color: #ffffff; cursor: move; font-weight: bold; padding: 0px; height: 15em; width: 700px; overflow: auto;'>";
	echo "</div>\n";

echo "</div>\n";
//===========================================================

	// Fonction de renseignement du champ qui doit obtenir le focus après validation
	echo "<script type='text/javascript'>

function affiche_bull_simp(login_eleve,id_classe,num_per1,num_per2) {
	document.getElementById('titre_entete_bull_simp').innerHTML='Bulletin simplifié de '+login_eleve+' période '+num_per1+' à '+num_per2;
	new Ajax.Updater($('corps_bull_simp'),'ajax_edit_limite.php?choix_edit=2&login_eleve='+login_eleve+'&id_classe='+id_classe+'&periode1='+num_per1+'&periode2='+num_per2,{method: 'get'});
}

function focus_suivant(num){
	temoin='';
	// La variable 'dernier' peut dépasser de l'effectif de la classe... mais cela n'est pas dramatique
	dernier=num+".$nombre_lignes."
	// On parcourt les champs à partir de celui de l'élève en cours jusqu'à rencontrer un champ existant
	// (pour réussir à passer un élève qui ne serait plus dans la période)
	// Après validation, c'est ce champ qui obtiendra le focus si on n'était pas à la fin de la liste.
	for(i=num;i<dernier;i++){
		suivant=i+1;
		if(temoin==''){
			if(document.getElementById('n'+suivant)){
				document.getElementById('info_focus').value=suivant;
				temoin=suivant;
			}
		}
	}

	document.getElementById('info_focus').value=temoin;
}

</script>\n";

//=========================
$insert_mass_appreciation_type=getSettingValue("insert_mass_appreciation_type");
if ($insert_mass_appreciation_type=="y") {
	// INSERT INTO setting SET name='insert_mass_appreciation_type', value='y';

	$sql="CREATE TABLE IF NOT EXISTS b_droits_divers (login varchar(50) NOT NULL default '', nom_droit varchar(50) NOT NULL default '', valeur_droit varchar(50) NOT NULL default '');";
	$create_table=mysqli_query($GLOBALS["mysqli"], $sql);

	// Pour tester:
	// INSERT INTO b_droits_divers SET login='toto', nom_droit='insert_mass_appreciation_type', valeur_droit='y';

	$sql="SELECT 1=1 FROM b_droits_divers WHERE login='".$_SESSION['login']."' AND nom_droit='insert_mass_appreciation_type' AND valeur_droit='y';";
	$res_droit=mysqli_query($GLOBALS["mysqli"], $sql);
	if(mysqli_num_rows($res_droit)>0) {
		$droit_insert_mass_appreciation_type="y";
	}
	else {
		$droit_insert_mass_appreciation_type="n";
	}

	if($droit_insert_mass_appreciation_type=="y") {
		$default_mass_appreciation="";
		if(getSettingValue('default_mass_appreciation')!="") {
			$default_mass_appreciation=getSettingValue('default_mass_appreciation');
		}

		$texte_infobulle="<div style='margin:1em; padding:0.2em; width:40em; border: 1px solid black; background-color: white; font-size: small; text-align:center;'>\n";
		$texte_infobulle.="<p>Insérer l'avis-type suivant<br />
<input type='radio' name='insert_mass_appreciation_type_mode' id='insert_mass_appreciation_type_mode_vide' value='vide' checked /><label for='insert_mass_appreciation_type_mode_vide'>pour tous les avis vides</label><br />
<input type='radio' name='insert_mass_appreciation_type_mode' id='insert_mass_appreciation_type_mode_ajout' value='ajout' /><label for='insert_mass_appreciation_type_mode_ajout'>en complément des avis</label><br />";
		$texte_infobulle.="<textarea name='no_anti_inject_ajout_a_textarea_vide' id='ajout_a_textarea_vide' cols='50'>$default_mass_appreciation</textarea><br />\n";

		$texte_infobulle.="<input type='checkbox' name='enregistrer_ajout_a_textarea_vide' id='enregistrer_ajout_a_textarea_vide' value='y' /><label for='enregistrer_ajout_a_textarea_vide'>Enregistrer cet avis-type comme avis-type par défaut</label><br />\n";

		$texte_infobulle.="<input type='button' name='ajouter_a_textarea_vide' value='Ajouter' onclick='ajoute_a_textarea_vide(); changement()' /><br />\n";

		$texte_infobulle.="<input type='button' name='button_vider_tous_les_avis' value='Vider tous les avis' onclick='vider_tous_les_avis(); changement()' /><br />\n";
		$texte_infobulle.="</div>\n";

		$texte_infobulle.="<script type='text/javascript'>
	function ajoute_a_textarea_vide() {
		if(document.getElementById('insert_mass_appreciation_type_mode_vide').checked==true) {
			mode_insert='vide';
		}
		else {
			mode_insert='ajout';
		}
		champs_textarea=document.getElementsByTagName('textarea');
		//alert('champs_textarea.length='+champs_textarea.length);
		for(i=0;i<champs_textarea.length;i++){
			if(champs_textarea[i].name!='no_anti_inject_ajout_a_textarea_vide') {
				if((mode_insert=='vide')&&(champs_textarea[i].value=='')) {
					champs_textarea[i].value=document.getElementById('ajout_a_textarea_vide').value;
				}
				else {
					if(mode_insert=='ajout') {
						champs_textarea[i].value+=document.getElementById('ajout_a_textarea_vide').value;
					}
				}
			}
		}
	}

	function vider_tous_les_avis() {
		var is_confirmed = confirm('ATTENTION : Vous avez demandé à vider tous les avis saisis pour cette classe ! Etes-vous sûr de vouloir vider ces avis ?');
		if(is_confirmed){
			champs_textarea=document.getElementsByTagName('textarea');
			for(i=0;i<champs_textarea.length;i++){
				if(champs_textarea[i].name!='no_anti_inject_ajout_a_textarea_vide') {
					champs_textarea[i].value='';
				}
			}
		}
	}
</script>\n";

		$titre_infobulle="Insertion en masse";
		$tabdiv_infobulle[]=creer_div_infobulle('div_insertion_en_masse',$titre_infobulle,"",$texte_infobulle,"",35,0,'y','y','n','n');

		echo "<div style='float:right;width:5em; text-align:center;' class='fieldset_opacite50'><a href='#' onclick=\"afficher_div('div_insertion_en_masse', 'y', 10, 10);return false;\">Insertion en masse</a></div>";

	}
}
//=========================


	$k=1;
	$commentaires_type_classe_periode=array();
	while ($k < $nb_periode) {
		// Existe-t-il des commentaires-types pour cette classe et cette période?
		$sql="select 1=1 from commentaires_types WHERE num_periode='$k' AND id_classe='$id_classe'";
		$res_test=mysqli_query($GLOBALS["mysqli"], $sql);
		if(mysqli_num_rows($res_test)!=0){
			$commentaires_type_classe_periode[$k]="y";
		}
		else{
			$commentaires_type_classe_periode[$k]="n";
		}
		$k++;
	}


	echo "<table width=\"750\" class='boireaus' border='1' cellspacing='2' cellpadding='5' summary=\"Synthèse de classe\">\n";
	echo "<tr>\n";
	echo "<th width=\"200\"><div align=\"center\"><b>&nbsp;</b></div></th>\n";
	echo "<th>\n";

	if(getSettingAOui('GepiAccesBulletinSimpleClasseEleve')) {
		echo "<div style='float:right; width:16px;margin-right:5px;'><img src='../images/icons/trombinoscope.png' width='16' height='16' title=\"L'appréciation sur le groupe-classe est visible des élèves\" alt=\"Appréciation sur le groupe-classe visible des élèves\" /></div>\n";
	}
	if(getSettingAOui('GepiAccesBulletinSimpleClasseResp')) {
		echo "<div style='float:right; width:16px;margin-right:5px;'><img src='../images/group16.png' width='16' height='16' title=\"L'appréciation sur le groupe-classe est visible des parents\" /></div>\n";
	}

	echo "<div align=\"center\"><b>Synthèse de classe</b>\n";

	//===============================================
	$tabdiv_infobulle[]=creer_div_infobulle('div_explication_cnil',"Saisies et CNIL","",$message_cnil_bons_usages,"",30,0,'y','y','n','n');
	// Paramètres concernant le délai avant affichage d'une infobulle via delais_afficher_div()
	// Hauteur de la bande testée pour la position de la souris:
	$hauteur_survol_infobulle=20;
	// Largeur de la bande testée pour la position de la souris:
	$largeur_survol_infobulle=100;
	// Délais en ms avant affichage:
	$delais_affichage_infobulle=500;
	//===============================================

	// 20121101: Mettre une infobulle CNIL
	echo " <a href='#' onclick=\"afficher_div('div_explication_cnil','y',10,-40);return false;\" onmouseover=\"delais_afficher_div('div_explication_cnil','y',10,-40, $delais_affichage_infobulle, $largeur_survol_infobulle, $hauteur_survol_infobulle);\"><img src='../images/info.png' width='20' height='20' title='CNIL : Règles de bon usage' /></a>";

	echo "</div>\n";
	echo "</th>\n";
	echo "</tr>\n";
	//========================

	$k='1';
	while ($k < $nb_periode) {
		$sql="SELECT * FROM synthese_app_classe WHERE (id_classe='$id_classe' AND periode='$k');";
		//echo "$sql<br />";
		$res_current_synthese=mysqli_query($GLOBALS["mysqli"], $sql);
		$current_synthese[$k] = @old_mysql_result($res_current_synthese, 0, "synthese");
		if ($current_synthese[$k] == '') {$current_synthese[$k] = ' -';}

		$k++;
	}

	//$i = "0";
	$num_id=10;

	$k='1';
	$alt=1;
	while ($k < $nb_periode) {
		$alt=$alt*(-1);
		if ($ver_periode[$k] != "N") {
			echo "<tr class='lig$alt'>\n<td><span title=\"$gepiClosedPeriodLabel\">";
			if(acces('/impression/avis_pdf.php', $_SESSION['statut'])) {
				echo "<a href='../impression/avis_pdf.php?id_classe=$id_classe&amp;periode_num=$k' onclick=\"return confirm_abandon (this, change, '$themessage')\" title=\"$nom_periode[$k] : Exporter au format PDF les avis du conseil de classe sur les élèves.\">";
				echo $nom_periode[$k];
				echo "</a>";
			}
			else {
				echo $nom_periode[$k];
			}
			echo "</span></td>\n";
		} else {
			echo "<tr class='lig$alt'>\n<td>";
			if(acces('/impression/avis_pdf.php', $_SESSION['statut'])) {
				echo "<a href='../impression/avis_pdf.php?id_classe=$id_classe&amp;periode_num=$k' onclick=\"return confirm_abandon (this, change, '$themessage')\" title=\"$nom_periode[$k] : Exporter au format PDF les avis du conseil de classe sur les élèves.\">";
				echo $nom_periode[$k];
				echo "</a>";
			}
			else {
				echo $nom_periode[$k];
			}
			echo "</td>\n";
		}

		if ($ver_periode[$k] != "O") {
			echo "<td>\n";
			echo "<textarea id=\"n".$k.$num_id."\" onKeyDown=\"clavier(this.id,event);\"  name=\"no_anti_inject_synthese_".$k."\" rows='2' cols='120' class='wrap' onchange=\"changement()\"";
			echo ">";
			//=========================

			echo "$current_synthese[$k]";
			echo "</textarea>\n";

			// 20160617
			echo "<div style='float:right; width:16px; margin-right:3px;' title=\"Corriger la ponctuation.\"><a href=\"#\" onclick=\"document.getElementById('n".$k.$num_id."').value=corriger_espaces_et_casse_ponctuation(document.getElementById('n".$k.$num_id."').value);changement();return false;\"><img src='../images/icons/wizard_ponctuation.png' class='icone16' alt='Ponctuation' /></a></div>";
			echo "</td>\n";
		}
		else {
			echo "<td><p class=\"medium\">";
			echo nl2br($current_synthese[$k]);
			echo "</p></td>\n";
		}
		echo "</tr>\n";
		$k++;
	}
	$num_id++;
	//$i++;
	echo "</table>\n<br />\n<br />\n";

	//=========================
	// 20150318: Désactivé parce que cela provoque une série de requêtes ajax au chargement de la page.
	//$chaine_test_vocabulaire="";
	//=========================

	$i = "0";
	//$num_id=10;
	while($i < $nombre_lignes) {
		$current_eleve_id_eleve = old_mysql_result($appel_donnees_eleves, $i, "id_eleve");
		$current_eleve_login = old_mysql_result($appel_donnees_eleves, $i, "login");
		$current_eleve_nom = old_mysql_result($appel_donnees_eleves, $i, "nom");
		$current_eleve_prenom = old_mysql_result($appel_donnees_eleves, $i, "prenom");
		$current_eleve_sexe = old_mysql_result($appel_donnees_eleves, $i, "sexe");

		//========================
		// AJOUT boireaus 20071115
		$sql="SELECT elenoet FROM eleves WHERE login='$current_eleve_login';";
		$res_ele=mysqli_query($GLOBALS["mysqli"], $sql);
		$lig_ele=mysqli_fetch_object($res_ele);
		$current_eleve_elenoet=$lig_ele->elenoet;

		// Photo...
		$photo=nom_photo($current_eleve_elenoet);
		$temoin_photo="";
		if("$photo"!=""){
			$titre="$current_eleve_nom $current_eleve_prenom";
			$texte="<div align='center'>\n";
			$texte.="<img src='".$photo."' width='150' alt=\"$current_eleve_nom $current_eleve_prenom\" />";
			$texte.="<br />\n";
			$texte.="</div>\n";

			$temoin_photo="y";

			$tabdiv_infobulle[]=creer_div_infobulle('photo_'.$current_eleve_login,$titre,"",$texte,"",14,0,'y','y','n','n');
		}
		//========================


		//========================
		// AJOUT boireaus 20071115
		/*
		echo "<table width=\"750\" border=1 cellspacing=2 cellpadding=5>\n";
		echo "<tr>\n";
		echo "<td width=\"200\"><div align=\"center\"><b>&nbsp;</b></div></td>\n";
		echo "<td><div align=\"center\"><b>$current_eleve_nom $current_eleve_prenom</b></div></td>\n";
		echo "</tr>\n";
		*/
		echo "<table width=\"750\" class='boireaus' border='1' cellspacing='2' cellpadding='5' summary=\"Elève $current_eleve_nom $current_eleve_prenom\">\n";
		echo "<tr>\n";
		echo "<th width=\"200\"><div align=\"center\"><b>&nbsp;</b></div></th>\n";
		echo "<th>";

		$num_per1=0;
		$id_premiere_classe="";
		$current_id_classe=array();
		$sql="SELECT id_classe,periode FROM j_eleves_classes WHERE login='$current_eleve_login' ORDER BY periode;";
		$res_classe=mysqli_query($GLOBALS["mysqli"], $sql);
		if(mysqli_num_rows($res_classe)>0) {
			$lig_classe=mysqli_fetch_object($res_classe);
			$id_premiere_classe=$lig_classe->id_classe;
			$current_id_classe[$lig_classe->periode]=$lig_classe->id_classe;
			$num_per1=$lig_classe->periode;
			$num_per2=$num_per1;
			while($lig_classe=mysqli_fetch_object($res_classe)) {
				$current_id_classe[$lig_classe->periode]=$lig_classe->id_classe;
				$num_per2=$lig_classe->periode;
			}
		}

		if(($id_premiere_classe!='')&&($acces_bull_simp=='y')) {
			echo "<div style='float:right; width: 17px; margin-right: 1px;'>\n";
			echo "<a href=\"#\" onclick=\"afficher_div('div_bull_simp','y',-100,40); affiche_bull_simp('$current_eleve_login','$id_premiere_classe','$num_per1','$num_per2');return false;\">";
			echo "<img src='../images/icons/bulletin_simp.png' width='17' height='17' alt='Bulletin simple toutes périodes en infobulle' title='Bulletin simple toutes périodes en infobulle' />";
			echo "</a>";
			echo "</div>\n";
		}

		echo "<div align=\"center\"><b><a href='../eleves/visu_eleve.php?ele_login=$current_eleve_login' target='_blank' title=\"Voir (dans un nouvel onglet) la fiche élève avec les onglets Élève, Enseignements, Bulletins, CDT, Absences,...\">$current_eleve_nom $current_eleve_prenom</a></b>\n";

		//==========================
		// AJOUT: boireaus 20071115
		// Lien photo...
		if($temoin_photo=="y"){
			//echo " <a href='#' onmouseover=\"afficher_div('photo_$current_eleve_login','y',-100,20);\"";
			echo " <a href=\"$photo\" onmouseover=\"delais_afficher_div('photo_$current_eleve_login','y',-100,20,1000,10,10);\" onclick=\"afficher_div('photo_$current_eleve_login','y',-100,20); return false;\" target='_blank' title=\"Afficher la photo de l'élève.\"";
			echo ">";
			echo "<img src='../mod_trombinoscopes/images/";
			if($current_eleve_sexe=="F") {
				echo "photo_f.png";
			}
			else{
				echo "photo_g.png";
			}
			echo "' class='icone20' alt='$current_eleve_nom $current_eleve_prenom' />";
			echo "</a>";
		}
		//==========================

		echo "</div></th>\n";
		echo "</tr>\n";
		//========================

		$k='1';
		while ($k < $nb_periode) {
			$current_eleve_avis_query[$k]= mysqli_query($GLOBALS["mysqli"], "SELECT * FROM avis_conseil_classe WHERE (login='$current_eleve_login' AND periode='$k')");
			$current_eleve_avis_t[$k] = @old_mysql_result($current_eleve_avis_query[$k], 0, "avis");
			// ***** AJOUT POUR LES MENTIONS *****
			$current_eleve_mention_t[$k] = @old_mysql_result($current_eleve_avis_query[$k], 0, "id_mention");
			// ***** FIN DE L'AJOUT POUR LES MENTIONS *****
			$current_eleve_login_t[$k] = $current_eleve_login."_t".$k;
			$k++;
		}

		// 20140226
		// Liste des avertissements
		/*
		if(getSettingAOui('active_mod_discipline')) {
			$tab_avertissement_fin_periode=get_tab_avertissement($current_eleve_login);
		}
		*/

		$k='1';
		$alt=1;
		while ($k < $nb_periode) {
			$alt=$alt*(-1);

			$result_test=0;
			if ($ver_periode[$k] != "O") {
				$call_eleve = mysqli_query($GLOBALS["mysqli"], "SELECT login FROM j_eleves_classes WHERE (login = '$current_eleve_login' and id_classe='$id_classe' and periode='$k')");
				$result_test = mysqli_num_rows($call_eleve);
			}

			if ($ver_periode[$k] != "N") {
				echo "<tr class='lig$alt'>\n<td><span title=\"$gepiClosedPeriodLabel\">".preg_replace("/ /", "&nbsp;", $nom_periode[$k])."</span>";

				if($ver_periode[$k]!="O") {
					echo "
				<div style='float:right; width:16px;' class='noprint'>
					<a href='saisie_avis2.php?periode_num=$k&id_classe=$id_classe&fiche=y&current_eleve_login=$current_eleve_login#app' title=\"Saisir l'avis pour ".$current_eleve_prenom." ".$current_eleve_nom." en période $k.\"  onclick=\"return confirm_abandon (this, change, '$themessage')\"><img src='../images/icons/edit16_ele.png' class='icone16' alt='Saisie indiv' /></a>
				</div>";
				}


				// 20121118
				// Si les parents ont l'accès aux bulletins, graphes,... on affiche s'ils ont l'accès aux appréciations à ce jour
				if((getSettingAOui('GepiAccesBulletinSimpleParent'))||
				(getSettingAOui('GepiAccesGraphParent'))||
				(getSettingAOui('GepiAccesBulletinSimpleEleve'))||
				(getSettingAOui('GepiAccesGraphEleve'))) {
					$affiche_slash_a="n";
					if(($acces_app_ele_resp=='manuel')&&($acces_classes_acces_appreciations)) {
						echo "<a href=\"javascript:alterner_visibilite_parent($i,$k)\">";
						$affiche_slash_a="y";
					}
					elseif(($acces_app_ele_resp=='manuel_individuel')&&($acces_classes_acces_appreciations)) {
						echo "<a href=\"javascript:alterner_visibilite_parent_indiv('$current_eleve_login',$i,$k)\">";
						$affiche_slash_a="y";
					}
					echo " <span id='span_acces_resp_".$i."_".$k."'>";
					//if($tab_acces_app_classe[$id_classe][$k]=="y") {
					if(isset($tab_acces_app_classe2[$id_classe][$k][$current_eleve_login])) {
						if($tab_acces_app_classe2[$id_classe][$k][$current_eleve_login]=="y") {
							echo "<img src='../images/icons/visible.png' width='19' height='16' alt='Appréciations visibles des parents/élèves.' title='A la date du jour (".$date_du_jour."), les appréciations de la période ".$k." sont visibles des parents/élèves.' />";
						}
						else {
							echo "<img src='../images/icons/invisible.png' width='19' height='16' alt='Appréciations non encore visibles des parents/élèves.' title=\"A la date du jour (".$date_du_jour."), les appréciations de la période ".$k." ne sont pas encore visibles des parents/élèves.
	$msg_acces_app_ele_resp\" />";
						}
					}
					echo " </span>";
					if($affiche_slash_a=="y") {
						echo " </a>";
					}
				}
			} elseif(($ver_periode[$k] != "O")&&($result_test>0)) {
				echo "<tr class='lig$alt'>\n<td>";
				echo "<a href='saisie_avis2.php?periode_num=".$k."&id_classe=".$id_classe."&fiche=y&current_eleve_login=".$current_eleve_login."&ind_eleve_login_suiv=$i#app' title=\"$nom_periode[$k] : Saisir l'avis du conseil de classe avec affichage du bulletin simplifié de $current_eleve_nom $current_eleve_prenom.\" onclick=\"return confirm_abandon(this, change, '$themessage')\">";
				echo $nom_periode[$k];
				echo "</a>";

				// 20121118
				// Si les parents ont l'accès aux bulletins, graphes,... on affiche s'ils ont l'accès aux appréciations à ce jour
				if((getSettingAOui('GepiAccesBulletinSimpleParent'))||
				(getSettingAOui('GepiAccesGraphParent'))||
				(getSettingAOui('GepiAccesBulletinSimpleEleve'))||
				(getSettingAOui('GepiAccesGraphEleve'))) {
					//if($tab_acces_app_classe[$id_classe][$k]=="y") {
					if((isset($tab_acces_app_classe2[$id_classe][$k][$current_eleve_login]))&&($tab_acces_app_classe2[$id_classe][$k][$current_eleve_login]=="y")) {
						echo " <img src='../images/icons/visible.png' width='19' height='16' alt='Appréciations visibles des parents/élèves.' title='A la date du jour (".$date_du_jour."), les appréciations de la période ".$k." sont visibles des parents/élèves.' />";
					}
					else {
						echo " <img src='../images/icons/invisible.png' width='19' height='16' alt='Appréciations non encore visibles des parents/élèves.' title=\"A la date du jour (".$date_du_jour."), les appréciations de la période ".$k." ne sont pas encore visibles des parents/élèves.
$msg_acces_app_ele_resp\" />";
					}
				}
			} else {
				echo "<tr class='lig$alt'>\n<td>";
				echo $nom_periode[$k];

				// 20121118
				// Si les parents ont l'accès aux bulletins, graphes,... on affiche s'ils ont l'accès aux appréciations à ce jour
				if((getSettingAOui('GepiAccesBulletinSimpleParent'))||
				(getSettingAOui('GepiAccesGraphParent'))||
				(getSettingAOui('GepiAccesBulletinSimpleEleve'))||
				(getSettingAOui('GepiAccesGraphEleve'))) {
					//if($tab_acces_app_classe[$id_classe][$k]=="y") {
					if((isset($tab_acces_app_classe2[$id_classe][$k][$current_eleve_login]))&&($tab_acces_app_classe2[$id_classe][$k][$current_eleve_login]=="y")) {
						echo " <img src='../images/icons/visible.png' width='19' height='16' alt='Appréciations visibles des parents/élèves.' title='A la date du jour (".$date_du_jour.") les appréciations de la période ".$k." sont visibles des parents/élèves.' />";
					}
					else {
						echo " <img src='../images/icons/invisible.png' width='19' height='16' alt='Appréciations non encore visibles des parents/élèves.' title=\"A la date du jour (".$date_du_jour.") les appréciations de la période ".$k." ne sont pas encore visibles des parents/élèves.
$msg_acces_app_ele_resp\" />";
					}
				}
			}

			if((isset($current_id_classe[$k]))&&($acces_bull_simp=='y')) {
				echo " <a href=\"#\" onclick=\"afficher_div('div_bull_simp','y',-100,20); affiche_bull_simp('$current_eleve_login','$current_id_classe[$k]','$k','$k');return false;\" alt='Bulletin simple en infobulle' title='Bulletin simple en infobulle'>";
				echo "<img src='../images/icons/bulletin_simp.png' width='17' height='17' alt='Bulletin simple de la période en infobulle' title='Bulletin simple de la période en infobulle' />";
				echo "</a>";
			}
			echo "</td>\n";


			if ($ver_periode[$k] != "O") {
				//$call_eleve = mysql_query("SELECT login FROM j_eleves_classes WHERE (login = '$current_eleve_login' and id_classe='$id_classe' and periode='$k')");
				//$result_test = mysql_num_rows($call_eleve);
				if ($result_test != 0) {
					echo "<td>\n";
					echo "<input type='hidden' name='log_eleve_".$k."[$i]' value=\"".$current_eleve_login_t[$k]."\" />\n";
					//echo "<textarea id=\"n".$k.$num_id."\" onKeyDown=\"clavier(this.id,event);\" name=\"no_anti_inject_avis_eleve_".$k."_".$i."\" rows='2' cols='120' class='wrap' onchange=\"changement()\"";
					echo "<textarea id=\"n".$k.$num_id."\" onKeyDown=\"clavier(this.id,event);\" name=\"no_anti_inject_avis_eleve_".$k."_".$i."\" rows='2' cols='120' class='wrap' onchange=\"changement()";

					if(getSettingValue('active_recherche_lapsus')!='n') {
						//echo " onBlur=\"ajaxVerifAvis('".$current_eleve_login_t[$k]."', '".$id_classe."', 'n".$k.$num_id."');\"";
						echo ";ajaxVerifAvis('".$current_eleve_login_t[$k]."', '".$id_classe."', 'n".$k.$num_id."');";
						// 20150318: Désactivé parce que ce la provoque une série de requêtes ajax au chargement de la page.
						//$chaine_test_vocabulaire.="ajaxVerifAvis('".$current_eleve_login_t[$k]."', '".$id_classe."', 'n".$k.$num_id."');\n";
					}
					echo "\"";

					echo ">";
					//=========================

					echo "$current_eleve_avis_t[$k]";
					echo "</textarea>\n";

					echo "<div style='float:right; width:16px' title=\"Éditer l'avis du conseil de classe pour $current_eleve_nom $current_eleve_prenom sur la période $k avec rappel du bulletin.\"><a href='saisie_avis2.php?periode_num=".$k."&id_classe=".$id_classe."&fiche=y&current_eleve_login=".$current_eleve_login."#app' onclick=\"return confirm_abandon (this, change, '$themessage')\"><img src='../images/icons/edit16_ele.png' class='icone16' alt='Éditer' /></a></div>";

					// 20160617
					echo "<div style='float:right; width:16px; margin-right:3px;' title=\"Corriger la ponctuation.\"><a href=\"#\" onclick=\"document.getElementById('n".$k.$num_id."').value=corriger_espaces_et_casse_ponctuation(document.getElementById('n".$k.$num_id."').value);changement();return false;\"><img src='../images/icons/wizard_ponctuation.png' class='icone16' alt='Ponctuation' /></a></div>";

					// ***** AJOUT POUR LES MENTIONS *****
					if(test_existence_mentions_classe($id_classe)) {
						echo ucfirst($gepi_denom_mention)." : ";
						echo champ_select_mention('mention_eleve_'.$i.'_'.$k,$id_classe,$current_eleve_mention_t[$k]);
						/*
						$selectedF="";
						$selectedM="";
						$selectedE="";
						$selectedB="";
						if($current_eleve_mention_t[$k]=='F') {$selectedF=" selected";}
						else if($current_eleve_mention_t[$k]=='M') {$selectedM=" selected";}
						else if($current_eleve_mention_t[$k]=='E') {$selectedE=" selected";}
						else {$selectedB=" selected";}
						echo "<select name='mention_eleve_".$i."_".$k."'>\n";
						echo "<option value='B'$selectedB> </option>\n";
						echo "<option value='E'$selectedE>Encouragements</option>\n";
						echo "<option value='M'$selectedM>Mention honorable</option>\n";
						echo "<option value='F'$selectedF>Félicitations</option>\n";
						echo "</select>\n";
						*/
					}
					// **** FIN DE L'AJOUT POUR LES MENTIONS ****

					// 20140226
					if(getSettingAOui('active_mod_discipline')) {
						if(acces_saisie_avertissement_fin_periode($current_eleve_login)) {
							echo "<div title=\"Saisir un ou des ".ucfirst($mod_disc_terme_avertissement_fin_periode)."\">
	<a href='../mod_discipline/saisie_avertissement_fin_periode.php?login_ele=$current_eleve_login&amp;periode=$k&amp;lien_refermer=y'";
						echo " onclick=\"afficher_saisie_avertissement_fin_periode('$current_eleve_login', $k, 'n', 'liste_avertissements_fin_periode_".$i."_$k');return false;\"";
						echo " style='color:black;' target='_blank'>
		<img src='../images/icons/balance_justice.png' class='icone20' alt=\"Avertissements de fin de période\" />
		<span class='bold' id='liste_avertissements_fin_periode_".$i."_$k'>".liste_avertissements_fin_periode($current_eleve_login, $k)."</span>
	</a>
</div>";
						}
						else {
							echo "<div title=\"".ucfirst($mod_disc_terme_avertissement_fin_periode)."\">
		<span class='bold'>".liste_avertissements_fin_periode($current_eleve_login, $k)."</span>
</div>";
						}
					}

					//echo "<a href='#' onClick=\"document.getElementById('textarea_courant').value='no_anti_inject_".$current_eleve_login_t[$k]."';afficher_div('commentaire_type','y',30,-150);return false;\">Ajout CC</a>";

					if((file_exists('saisie_commentaires_types.php'))
						&&(($_SESSION['statut'] == 'professeur')&&(getSettingValue("GepiRubConseilProf")=='yes')&&(getSettingValue('CommentairesTypesPP')=='yes'))
						||(($_SESSION['statut'] == 'scolarite')&&(getSettingValue("GepiRubConseilScol")=='yes')&&(getSettingValue('CommentairesTypesScol')=='yes'))
						||(($_SESSION['statut'] == 'cpe')&&((getSettingValue("GepiRubConseilCpe")=='yes')||(getSettingValue("GepiRubConseilCpeTous")=='yes'))&&(getSettingValue('CommentairesTypesCpe')=='yes'))) {

						if($commentaires_type_classe_periode[$k]=="y"){
							echo "<a href='#' onClick=\"document.getElementById('textarea_courant').value='n".$k.$num_id."';afficher_div('commentaire_type','y',30,-50);return false;\">Ajouter un commentaire-type</a>\n";
						}
					}

					//echo "<div id='div_verif_n".$k.$num_id."' style='color:red;'></div>\n";
					// Espace pour afficher les éventuelles fautes de frappe
					echo "<div id='div_verif_n".$k.$num_id."' style='color:red;'>";
					// 20150316
					if(getSettingValue('active_recherche_lapsus')!='n') {
						echo teste_lapsus($current_eleve_avis_t[$k]);
					}
					echo "</div>\n";

					echo "</td>\n";
				} else {
					echo "<td><p>$current_eleve_avis_t[$k]&nbsp;</p></td>\n";
				}
			}
			else {
				echo "<td><p class=\"medium\">";
				echo "$current_eleve_avis_t[$k]";
				echo "</p>\n";
				// ***** AJOUT POUR LES MENTIONS *****
				if((!isset($tableau_des_mentions_sur_le_bulletin))||(!is_array($tableau_des_mentions_sur_le_bulletin))||(count($tableau_des_mentions_sur_le_bulletin)==0)) {
					$tableau_des_mentions_sur_le_bulletin=get_mentions();
				}

				if(isset($tableau_des_mentions_sur_le_bulletin[$current_eleve_mention_t[$k]])) {
					echo "<p class=\"medium\"><b> ".ucfirst($gepi_denom_mention)." : ";
					echo $tableau_des_mentions_sur_le_bulletin[$current_eleve_mention_t[$k]];
					echo "</b></p>\n";
				}
				// ***** FIN DE L'AJOUT POUR LES MENTIONS *****

				// 20140226
				if(getSettingAOui('active_mod_discipline')) {
					echo "<div title=\"".ucfirst($mod_disc_terme_avertissement_fin_periode)."\">
		<span class='bold'>".liste_avertissements_fin_periode($current_eleve_login, $k)."</span>
</div>";
				}
				echo "</td>\n";
			}
			echo "</tr>\n";
			$k++;
		}
		//echo "</tr>";
		$num_id++;
		$i++;

		// 20160410
		if((getSettingAOui('active_mod_orientation'))&&(mef_avec_proposition_orientation("","",$current_eleve_login))) {
			$chaine_voeux_orientation="";
			$chaine_orientation_proposee="";

			//=========================
			// Voeux
			$edit_voeu_orientation="";
			if(isset($tab_orientation_classe_courante['voeux'][$current_eleve_login])) {
				/*
				for($loop_voeu=1;$loop_voeu<=count($tab_orientation_classe_courante['voeux'][$current_eleve_login]);$loop_voeu++) {
					$chaine_voeux_orientation.="<b>".$loop_voeu.".</b> ".$tab_orientation_classe_courante['voeux'][$current_eleve_login][$loop_voeu]['designation'];
					if(($tab_orientation_classe_courante['voeux'][$current_eleve_login][$loop_voeu]['commentaire']!="")&&($tab_orientation_classe_courante['voeux'][$current_eleve_login][$loop_voeu]['commentaire']!=$tab_orientation_classe_courante['voeux'][$current_eleve_login][$loop_voeu]['designation'])) {
						$chaine_voeux_orientation.=" (".$tab_orientation_classe_courante['orientation_proposee'][$current_eleve_login][$loop_voeu]['commentaire'].")";
					}
					$chaine_voeux_orientation.="<br />\n";
				}
				*/
				$chaine_voeux_orientation=get_liste_voeux_orientation($current_eleve_login);
			}
			if($acces_saisie_voeux_orientation) {
				// Ouvrir vers ../mod_orientation/saisie_voeux.php si l'ouverture en JS/Ajax n'est pas possible... et afficher les voeux déjà saisis.
				//$edit_voeu_orientation="<div style='float:right; width:16px;'><a href='../mod_orientation/saisie_voeux.php?id_classe=$id_classe' onclick=\"afficher_saisie_voeux_orientation('$current_eleve_login', $current_eleve_id_eleve);return false;\" target='_blank'><img src='../images/edit16.png' class='icone16' alt='Modifier' /></a></div>";
				$edit_voeu_orientation="<div style='float:right; width:16px;'><a href='../mod_orientation/saisie_voeux.php?id_classe=$id_classe' onclick=\"afficher_saisie_voeux_orientation('$current_eleve_login', $current_eleve_id_eleve);return false;\" target='_blank'><img src='../images/edit16.png' class='icone16' alt='Modifier' /></a></div>";

				$titre_infobulle="Saisie des voeux (".$current_eleve_nom." ".$current_eleve_prenom.")";
				$texte_infobulle="<form action='../mod_orientation/saisie_voeux.php' id='form_saisie_voeux_orientation_".$current_eleve_id_eleve."' method='post' target='_blank'>
	".add_token_field()."
	<input type='hidden' name='login_eleve' id='saisie_voeux_login_eleve_".$current_eleve_id_eleve."' value='$current_eleve_login' />
	<input type='hidden' name='id_eleve' id='saisie_voeux_id_eleve_".$current_eleve_id_eleve."' value='$current_eleve_id_eleve' />
	".get_select_voeux_orientation($current_eleve_login)."
	<!--p><input type='submit' value='Valider' /></p-->
	<p><input type='button' value='Valider' onclick=\"valider_saisie_voeux($current_eleve_id_eleve)\" /></p>
</form>";
				$tabdiv_infobulle[]=creer_div_infobulle('div_infobulle_voeux_'.$current_eleve_id_eleve, $titre_infobulle, "",$texte_infobulle,"",35,0,'y','y','n','n');

			}

			//=========================
			// Orientation
			$edit_orientation_proposee="";
			if(isset($tab_orientation_classe_courante['orientation_proposee'][$current_eleve_login])) {
				/*for($loop_op=1;$loop_op<=count($tab_orientation_classe_courante['orientation_proposee'][$current_eleve_login]);$loop_op++) {
					$chaine_orientation_proposee.="<b>".$loop_op.".</b> ".$tab_orientation_classe_courante['orientation_proposee'][$current_eleve_login][$loop_op]['designation'];
					if(($tab_orientation_classe_courante['orientation_proposee'][$current_eleve_login][$loop_op]['commentaire']!="")&&($tab_orientation_classe_courante['orientation_proposee'][$current_eleve_login][$loop_op]['commentaire']!=$tab_orientation_classe_courante['orientation_proposee'][$current_eleve_login][$loop_op]['designation'])) {
						$chaine_orientation_proposee.=" (".$tab_orientation_classe_courante['orientation_proposee'][$current_eleve_login][$loop_op]['commentaire'].")";
					}
					$chaine_orientation_proposee.="<br />\n";
				}
				*/
				$chaine_orientation_proposee=get_liste_orientations_proposees($current_eleve_login);
				if($chaine_orientation_proposee!="") {
					$chaine_orientation_proposee.="<br />";
				}
				$chaine_orientation_proposee.=get_avis_orientations_proposees($current_eleve_login);
				//$chaine_orientation_proposee.=nl2br(get_avis_orientations_proposees($current_eleve_login));
			}
			if($acces_saisie_orientation) {
				// Ouvrir vers ../mod_orientation/saisie_orientation.php si l'ouverture en JS/Ajax n'est pas possible... et afficher les orientations déjà saisies.
				$edit_orientation_proposee="<div style='float:right; width:16px;'><a href='../mod_orientation/saisie_orientation.php?id_classe=$id_classe' onclick=\"afficher_saisie_orientation_proposee('$current_eleve_login', $current_eleve_id_eleve);return false;\" target='_blank'><img src='../images/edit16.png' class='icone16' alt='Modifier' /></a></div>";

				$titre_infobulle="Saisie des orientations (".$current_eleve_nom." ".$current_eleve_prenom.")";
				$texte_infobulle="<form action='../mod_orientation/saisie_orientation.php' id='form_saisie_orientation_".$current_eleve_id_eleve."' method='post' target='_blank'>
	".add_token_field()."
	<input type='hidden' name='login_eleve' id='saisie_orientation_login_eleve_".$current_eleve_id_eleve."' value='$current_eleve_login' />
	<input type='hidden' name='id_eleve' id='saisie_orientation_id_eleve_".$current_eleve_id_eleve."' value='$current_eleve_id_eleve' />
	".get_select_orientations_proposees($current_eleve_login)."
	".get_champ_avis_orientations_proposees($current_eleve_login)."
	<!--p><input type='submit' value='Valider' /></p-->
	<p><input type='button' value='Valider' onclick=\"valider_saisie_orientation($current_eleve_id_eleve)\" /></p>
</form>";
				$tabdiv_infobulle[]=creer_div_infobulle('div_infobulle_orientation_'.$current_eleve_id_eleve, $titre_infobulle, "",$texte_infobulle,"",40,0,'y','y','n','n');
			}
			//=========================

			$alt=$alt*(-1);
			echo "
	<tr class='lig$alt'>
		<td>Orientation</td>
		<td>
			<table class='boireaus boireaus_alt2' width='100%'>
				<tr>
					<th>Voeux</th>
					<th>Orientation proposée/conseillée</th>
				</tr>
				<tr>
					<td style='text-align:left;vertical-align:top;'>".$edit_voeu_orientation."<div id='div_voeu_".$current_eleve_id_eleve."'>$chaine_voeux_orientation</div></td>
					<td style='text-align:left;vertical-align:top;'>".$edit_orientation_proposee."<div id='div_orientation_proposee_".$current_eleve_id_eleve."'>$chaine_orientation_proposee</div></td>
				</tr>
			</table>
		</td>
	</tr>";
		}
		echo "</table>\n<br />\n<br />\n";

	}


	if((file_exists('saisie_commentaires_types.php'))
		&&(($_SESSION['statut'] == 'professeur')&&(getSettingValue("GepiRubConseilProf")=='yes')&&(getSettingValue('CommentairesTypesPP')=='yes'))
		||(($_SESSION['statut'] == 'scolarite')&&(getSettingValue("GepiRubConseilScol")=='yes')&&(getSettingValue('CommentairesTypesScol')=='yes'))
		||(($_SESSION['statut'] == 'cpe')&&((getSettingValue("GepiRubConseilCpe")=='yes')||(getSettingValue("GepiRubConseilCpeTous")=='yes'))&&(getSettingValue('CommentairesTypesCpe')=='yes'))) {
		//include('saisie_commentaires_types.php');
		//include('saisie_commentaires_types2.php');
		include('saisie_commentaires_types2b.php');
		//echo "AAAAAAAAAAAAA";
	}


	if ($test_periode_ouverte == 'yes') {
		?>
		<input type='hidden' name='is_posted' value="yes" />
		<input type='hidden' name='id_classe' value=<?php echo "$id_classe";?> />
		<center>
			<div id="fixe">
				<?php
					//=========================
					$chaine_date_conseil_classe=affiche_date_prochain_conseil_de_classe_classe($id_classe);
					if(($chaine_date_conseil_classe=="")&&(acces("/classes/dates_classes.php", $_SESSION['statut']))) {
						$chaine_date_conseil_classe="<p style='font-size:xx-small; color:red;' title=\"Faire apparaitre une date de conseil de classe en page d'accueil pour les personnes souhaitées (professeurs, cpe, scolarité, élèves, parents) est un plus pour les utilisateurs.\nCela permet de plus un accès plus direct aux différentes saisies à effectuer en période de conseil de classe.\n\nSi le conseil de classe est passé, ne tenez pas compte de ce message.\">Aucune date<br />de conseil de classe<br />n'a été définie dans Gepi.<br /><a href='../classes/dates_classes.php' target='_blank'>Définir une date de conseil.</a></p>";
					}
					echo $chaine_date_conseil_classe."<br />";
					//=========================
					if(getSettingAOui('aff_temoin_check_serveur')) {
						temoin_check_srv();
					}
				?>
				<input type='submit' value='Enregistrer' title="Enregistrer les avis saisis" />

		<!-- DIV destiné à afficher un décompte du temps restant pour ne pas se faire piéger par la fin de session -->
		<div id='decompte' title="La session ne sera plus valide, si vous ne consultez pas une page
ou ne validez pas ce formulaire avant le nombre de secondes indiqué."></div>

		<!-- Champ destiné à recevoir la valeur du champ suivant celui qui a le focus pour redonner le focus à ce champ après une validation -->
		<input type='hidden' id='info_focus' name='champ_info_focus' value='' size='3' />

		</div></center>
		<br /><br /><br /><br />

		<?php
			if((($acces_app_ele_resp=='manuel')||($acces_app_ele_resp=='manuel_individuel'))&&($acces_classes_acces_appreciations)) {
				echo "<script type='text/javascript'>
	function alterner_visibilite_parent(num_ele, periode_num) {
		identifiant='span_acces_resp_'+num_ele+'_'+periode_num;

		new Ajax.Updater($(identifiant),'./saisie_avis1.php?id_classe=$id_classe&periode_num='+periode_num+'&mode=modifier_visibilite_parents&mode_js=y".add_token_in_url(false)."',{method: 'get'});

		setTimeout('maj_acces_resp('+num_ele+', '+periode_num+')', 3000);
	}

	function alterner_visibilite_parent_indiv(login_ele, num_ele, periode_num) {
		identifiant='span_acces_resp_'+num_ele+'_'+periode_num;

		new Ajax.Updater($(identifiant),'./saisie_avis1.php?login_eleve='+login_ele+'&id_classe=$id_classe&periode_num='+periode_num+'&mode=modifier_visibilite_parents&mode_js=y".add_token_in_url(false)."',{method: 'get'});

		// C'est une modif individuelle
		// On ne met pas à jour le témoin pour les autres élèves
		//setTimeout('maj_acces_resp('+num_ele+', '+periode_num+')', 3000);
	}

	function maj_acces_resp(num_ele, periode_num) {
		for(j=0;j<$i;j++) {
			if(j!=num_ele) {
				if(document.getElementById('span_acces_resp_'+j+'_'+periode_num)) {
					document.getElementById('span_acces_resp_'+j+'_'+periode_num).innerHTML=document.getElementById(identifiant).innerHTML;
				}
			}
		}
	}
</script>\n";
			}

			// Il faudra permettre de n'afficher ce décompte que si l'administrateur le souhaite.

			echo "<script type='text/javascript'>
";

//20150318
//$chaine_test_vocabulaire;

			echo "
cpt=".$tmp_timeout.";
compte_a_rebours='y';

function decompte(cpt){
	if(compte_a_rebours=='y'){
		document.getElementById('decompte').innerHTML=cpt;
		if(cpt>0){
			cpt--;
		}

		setTimeout(\"decompte(\"+cpt+\")\",1000);
	}
	else{
		document.getElementById('decompte').style.display='none';
	}
}

decompte(cpt);

";

		// Après validation, on donne le focus au champ qui suivait celui qui vien d'être rempli
		if(isset($_POST['champ_info_focus'])){
			if($_POST['champ_info_focus']!=""){
				echo "// On positionne le focus...
			document.getElementById('n".$_POST['champ_info_focus']."').focus();
		\n";
			}
		}

		echo "</script>\n";

	}

	// 20160410
	if((getSettingAOui('active_mod_orientation'))&&(mef_avec_proposition_orientation($id_classe))) {
		/*
		$titre_infobulle="Saisie des voeux (<span id='span_titre_infobulle_saisie_voeux'></span>)";
		$texte_infobulle="<form action='../mod_orientation/saisie_voeux.php' id='form_saisie_voeux_orientation' method='post' target='_blank'>
	".add_token_field()."
	<input type='hidden' name='login_eleve' id='saisie_voeux_login_eleve' value='' />
	<input type='hidden' name='id_eleve' id='saisie_voeux_id_eleve' value='' />
	<div id='div_champs_saisie_voeux_orientation'></div>
	<!--p><input type='submit' value='Valider' /></p-->
	<p><input type='button' value='Valider' onclick=\"valider_saisie_voeux()\" /></p>
</form>";
		$tabdiv_infobulle[]=creer_div_infobulle('div_infobulle_voeux',$titre_infobulle,"",$texte_infobulle,"",35,0,'y','y','n','n');
		*/

//'div_infobulle_voeux_'.$current_eleve_id_eleve

		echo "<script type='text/javascript'>
	function afficher_saisie_voeux_orientation(login_ele, id_eleve) {
		afficher_div('div_infobulle_voeux_'+id_eleve, 'y', 10, 10);
	}

	function valider_saisie_voeux(id_eleve) {
		//id_eleve=document.getElementById('saisie_voeux_id_eleve').value;
		csrf_alea=document.getElementById('csrf_alea').value;

		var tab_voeux=new Array();
		var tab_commentaires_voeux=new Array();
		for(i=1;i<=$OrientationNbMaxVoeux;i++) {
			if(document.getElementById('voeu_'+id_eleve+'_'+i)) {
				//alert(document.getElementById('voeu_'+id_eleve+'_'+i).selectedIndex);
				tab_voeux[i]=document.getElementById('voeu_'+id_eleve+'_'+i).options[document.getElementById('voeu_'+id_eleve+'_'+i).selectedIndex].value;
				//alert('tab_voeux['+i+']='+tab_voeux[i]);
				//tab_commentaires_voeux[i]=document.getElementById('commentaire_voeu_'+id_eleve+'_'+i).value;
				tab_commentaires_voeux[i]=encodeURIComponent(document.getElementById('commentaire_voeu_'+id_eleve+'_'+i).value);
			}
		}

		new Ajax.Updater($('div_voeu_'+id_eleve),'../mod_orientation/saisie_voeux.php',{method: 'post',
		parameters: {
			id_eleve: id_eleve,
			mode: 'saisie_voeux_eleve',";
		for($loop=1;$loop<=$OrientationNbMaxVoeux;$loop++) {
			echo "
			tab_voeux_$loop: tab_voeux[$loop],
			tab_commentaires_voeux_$loop: tab_commentaires_voeux[$loop],";
		}
		echo "
			tab_commentaires_voeux: tab_commentaires_voeux,
			csrf_alea: csrf_alea
		}});

		cacher_div('div_infobulle_voeux_'+id_eleve);

	}

	function afficher_saisie_orientation_proposee(login_ele, id_eleve) {
		afficher_div('div_infobulle_orientation_'+id_eleve, 'y', 10, 10);
	}

	function valider_saisie_orientation(id_eleve) {
		csrf_alea=document.getElementById('csrf_alea').value;

		var tab_orientation=new Array();
		var tab_commentaires_orientation=new Array();
		for(i=1;i<=$OrientationNbMaxOrientation;i++) {
			if(document.getElementById('orientation_'+id_eleve+'_'+i)) {
				//alert(document.getElementById('orientation_'+id_eleve+'_'+i).selectedIndex);
				tab_orientation[i]=document.getElementById('orientation_'+id_eleve+'_'+i).options[document.getElementById('orientation_'+id_eleve+'_'+i).selectedIndex].value;
				//alert('tab_orientation['+i+']='+tab_orientation[i]);
				//tab_commentaires_orientation[i]=document.getElementById('commentaire_orientation_'+id_eleve+'_'+i).value;
				tab_commentaires_orientation[i]=encodeURIComponent(document.getElementById('commentaire_orientation_'+id_eleve+'_'+i).value);
			}
		}

		avis_orientation='';
		if(document.getElementById('avis_orientation_'+id_eleve)) {
			avis_orientation=document.getElementById('avis_orientation_'+id_eleve).value;
			avis_orientation=encodeURIComponent(avis_orientation);
		}

		new Ajax.Updater($('div_orientation_proposee_'+id_eleve),'../mod_orientation/saisie_orientation.php',{method: 'post',
		parameters: {
			id_eleve: id_eleve,
			mode: 'saisie_orientation_eleve',";
		for($loop=1;$loop<=$OrientationNbMaxOrientation;$loop++) {
			echo "
			tab_orientation_$loop: tab_orientation[$loop],
			tab_commentaires_orientation_$loop: tab_commentaires_orientation[$loop],";
		}
		echo "
			tab_commentaires_orientation: tab_commentaires_orientation,
			avis_orientation: avis_orientation,
			csrf_alea: csrf_alea
		}});

		cacher_div('div_infobulle_orientation_'+id_eleve);

	}
</script>";
	}
}

?>
<div id="debug_fixe" style="position: fixed; bottom: 20%; right: 5%;"></div>
</form>
<?php require("../lib/footer.inc.php");?>
