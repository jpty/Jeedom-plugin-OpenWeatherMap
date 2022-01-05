<?php
if (!isConnect('admin')) {
	throw new Exception('{{401 - Accès non autorisé}}');
}
$plugin = plugin::byId('owm');
sendVarToJS('eqType', $plugin->getId());
$eqLogics = eqLogic::byType($plugin->getId());
?>

<div class="row row-overflow">
	<div class="col-xs-12 eqLogicThumbnailDisplay">
		<legend><i class="fas fa-cog"></i> {{Gestion}}</legend>
		<div class="eqLogicThumbnailContainer">
			<div class="cursor eqLogicAction logoPrimary" data-action="add">
				<i class="fas fa-plus-circle"></i>
				<br/>
				<span>{{Ajouter}}</span>
			</div>
			<div class="cursor eqLogicAction logoSecondary" data-action="gotoPluginConf">
				<i class="fas fa-wrench"></i>
				<br/>
				<span >{{Configuration}}</span>
			</div>
		</div>
		<legend><i class="icon meteo-soleil"></i> {{Mes Météos}}</legend>
		<input class="form-control" placeholder="{{Rechercher}}" id="in_searchEqlogic" />
		<div class="eqLogicThumbnailContainer">
			<?php
			foreach ($eqLogics as $eqLogic) {
				$opacity = ($eqLogic->getIsEnable()) ? '' : 'disableCard';
				echo '<div class="eqLogicDisplayCard cursor '.$opacity.'" data-eqLogic_id="' . $eqLogic->getId() . '">';
				echo '<img src="' . $plugin->getPathImgIcon() . '"/>';
				echo '<br/>';
				echo '<span class="name">' . $eqLogic->getHumanName(true, true) . '</span>';
				echo '</div>';
			}
			?>
		</div>
	</div>
	
	<div class="col-xs-12 eqLogic" style="display: none;">
		<div class="input-group pull-right" style="display:inline-flex">
			<span class="input-group-btn">
				<a class="btn btn-default eqLogicAction btn-sm roundedLeft" data-action="configure"><i class="fas fa-cogs"></i> {{Configuration avancée}}</a><a class="btn btn-sm btn-success eqLogicAction" data-action="save"><i class="fas fa-check-circle"></i> {{Sauvegarder}}</a><a class="btn btn-danger btn-sm eqLogicAction roundedRight" data-action="remove"><i class="fas fa-minus-circle"></i> {{Supprimer}}</a>
			</span>
		</div>
		<ul class="nav nav-tabs" role="tablist">
			<li role="presentation"><a class="eqLogicAction cursor" aria-controls="home" role="tab" data-action="returnToThumbnailDisplay"><i class="fas fa-arrow-circle-left"></i></a></li>
			<li role="presentation" class="active"><a href="#eqlogictab" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-tachometer-alt"></i> {{Equipement}}</a></li>
			<li role="presentation"><a href="#commandtab" aria-controls="profile" role="tab" data-toggle="tab"><i class="fas fa-list-alt"></i> {{Commandes}}</a></li>
		</ul>
		<div class="tab-content" style="height:calc(100% - 50px);overflow:auto;overflow-x: hidden;">
			<div role="tabpanel" class="tab-pane active" id="eqlogictab">
				<br/>
				<form class="form-horizontal">
					<fieldset>
						<div class="form-group">
							<label class="col-sm-3 control-label">{{Nom de l'équipement météo}}</label>
							<div class="col-sm-3">
								<input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display : none;" />
								<input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="{{Nom de l'équipement météo}}"/>
							</div>
						</div>
						<div class="form-group">
							<label class="col-sm-3 control-label" >{{Objet parent}}</label>
							<div class="col-sm-3">
								<select id="sel_object" class="eqLogicAttr form-control" data-l1key="object_id">
									<option value="">{{Aucun}}</option>
									<?php
									$options = '';
									foreach ((jeeObject::buildTree(null,false)) as $object) {
										$options .= '<option value="' . $object->getId() . '">' . str_repeat('&nbsp;&nbsp;', $object->getConfiguration('parentNumber')) . $object->getName() . '</option>';
									}
									echo $options;
									?>
								</select>
							</div>
						</div>
						<div class="form-group">
							<label class="col-sm-3 control-label">{{Catégorie}}</label>
							<div class="col-sm-6">
								<?php
								foreach (jeedom::getConfiguration('eqLogic:category') as $key => $value) {
									echo '<label class="checkbox-inline">';
									echo '<input type="checkbox" class="eqLogicAttr" data-l1key="category" data-l2key="' . $key . '" />' . $value['name'];
									echo '</label>';
								}
								?>
							</div>
						</div>
						<div class="form-group">
							<label class="col-sm-3 control-label"></label>
							<div class="col-sm-9">
								<label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isEnable" checked/>{{Activer}}</label>
								<label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isVisible" checked/>{{Visible}}</label>
							</div>
						</div>
						<div class="form-group">
							<label class="col-sm-3 control-label">{{ID ville OpenWeather à rechercher <a target=_blank href="https://openweathermap.org/find?q=">ICI</a><br/>et à récupérer dans l'URL de la ville choisie.}}</label>
							<div class="col-sm-3">
                <input type="text" class="eqLogicAttr configuration form-control" data-l1key="configuration" data-l2key="cityID" />
              </div>
<!--
              <div class="col-sm-3">
                <a class="btn btn-default" id='btnSearchCity'><i class="fas fa-search"></i> {{Trouver la ville}}</a>
							</div>
-->
						</div>
						<div class="form-group">
							<label class="col-sm-3 control-label">{{Latitude}}</label>
							<div class="col-sm-3">
                <input type="text" class="eqLogicAttr configuration form-control" data-l1key="configuration" data-l2key="latitude" disabled/>
							</div>
						</div>
						<div class="form-group">
							<label class="col-sm-3 control-label">{{Longitude}}</label>
							<div class="col-sm-3">
                <input type="text" class="eqLogicAttr configuration form-control" data-l1key="configuration" data-l2key="longitude" disabled/>
							</div>
						</div>
						<div class="form-group">
							<label class="col-sm-3 control-label">{{Ville}}</label>
							<div class="col-sm-3">
                <input type="text" class="eqLogicAttr configuration form-control" data-l1key="configuration" data-l2key="cityName" disabled/>
							</div>
						</div>
						<div class="form-group">
							<label class="col-sm-3 control-label">Pays</label>
							<div class="col-sm-3">
                <input type="text" class="eqLogicAttr configuration form-control" data-l1key="configuration" data-l2key="country" disabled/>
							</div>
						</div>
						<div class="form-group">
							<label class="col-sm-3 control-label" >{{Langue des dates}}</label>
							<div class="col-sm-3">
								<select id="sel_dateLoc" class="eqLogicAttr configuration form-control" data-l1key="configuration" data-l2key="dateLoc">
									<option value="">{{Aucune}}</option>
									<?php
                  exec('locale -a',$output,$retval);
                  if($retval == 0)  { //print_r($output);
                    $options = '';
                    foreach ($output as $object) {
                      $options .= '<option value="' . $object . '">' . $object . '</option>';
                    }
                    echo $options;
                  }
									?>
								</select>
							</div>
            </div>
<!--
						<div class="form-group">
							<label class="col-sm-3 control-label" ></label>
							<div class="col-sm-9">
                <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="fullMobileDisplay" />{{Affichage complet en mobile}}</label>
							</div>
						</div>
-->

            <div class="form-group">
              <label class="col-sm-3 control-label">Images du plugin</label>
              <div class="col-sm-9">
                <label class="radio-inline"><input type="radio" id="50" class="eqLogicAttr" name="Images" data-l1key="configuration" data-l2key="internal_images" />Images</label>
                <label class="radio-inline"><input type="radio" id="60" class="eqLogicAttr" name="Images" data-l1key="configuration" data-l2key="owm_images" />OpenWeatherMap images</label>
                <label class="radio-inline"><input type="radio" id="60" class="eqLogicAttr" name="Images" data-l1key="configuration" data-l2key="wi_icons" />Weather icons</label>
              </div>
            </div>


            <div class="form-group">
							<label class="col-sm-3 control-label" ></label>
              <div class="col-sm-3">
                <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="forecast1h" />{{Affichage des prévisions par tranche d'une heure}}</label>
							</div>
						</div>
            <div class="form-group">
							<label class="col-sm-3 control-label" ></label>
              <div class="col-sm-3">
                <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="forecast3h" />{{Affichage des prévisions par tranche de 3 heures}}</label>
							</div>
						</div>
            <div class="form-group">
							<label class="col-sm-3 control-label" ></label>
              <div class="col-sm-3">
                <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="forecastDaily" />{{Affichage des prévisions journalières}}</label>
							</div>
						</div>
            <div class="form-group">
							<label class="col-sm-3 control-label" ></label>
							<div class="col-sm-9">
								<label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="rainForecast" />{{Affichage des prévisions de pluie dans l'heure}}</label>
							</div>
						</div>
            <div class="form-group">
              <label class="col-sm-3 control-label"></label>
              <div class="col-sm-3">
								<label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="alerts" />{{Affichage des alertes météo}}</label>

              </div>
            </div>
            <div class="form-group">
              <label class="col-sm-3 control-label"></label>
              <div class="col-sm-3">
								<label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="air_quality" />{{Affichage qualité de l'air}}</label>

              </div>
            </div>
<!--
            <div class="form-group">
              <label class="col-sm-3 control-label">Affichage direction du vent</label>
              <div class="col-sm-3">
                <label class="radio-inline"><input type="radio" id="50" class="eqLogicAttr" name="Freq" data-l1key="configuration" data-l2key="weathercock" />Girouette</label>
                <label class="radio-inline"><input type="radio" id="60" class="eqLogicAttr" name="Freq" data-l1key="configuration" data-l2key="windsock" />Manche à air</label>
              </div>
            </div>
-->
             <div class="form-group">
							<label class="col-sm-3 control-label" >{{Commentaires}}</label>
							<div class="col-sm-3">
								<input type="string" class="eqLogicAttr configuration form-control" data-l1key="configuration" data-l2key="comments"/>
							</div>
						</div>
					</fieldset>
				</form>
			</div>
			<div role="tabpanel" class="tab-pane" id="commandtab">
				<br/>
				<table id="table_cmd" class="table table-bordered table-condensed">
					<thead>
						<tr>
							<th>Id</th><th>LogicalId</th><th>{{Nom}}</th><th>Type</th><th>Sous-type</th><th>{{Options}}</th><th>{{Action}}</th>
						</tr>
					</thead>
					<tbody>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>

<?php include_file('desktop', 'owm', 'js', 'owm');?>
<?php include_file('core', 'plugin.template', 'js');?>
