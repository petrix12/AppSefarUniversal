@extends('adminlte::page')

@section('title', 'Contrato')

@section('content_header')
    <h1>Contrato</h1>
    <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@stop

@section('content')

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.2/jquery.min.js" integrity="sha512-tWHlutFnuG0C6nQRlpvrEhE4QpkG1nn2MOUMWmUeRePl4e3Aki0VB6W1v3oLjFtd0hVOtRQ9PHpSfN6u6/QXkQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

    <script src="https://cdn03.jotfor.ms/static/prototype.forms.js?3.3.41961" type="text/javascript"></script>
<script src="https://cdn01.jotfor.ms/static/jotform.forms.js?3.3.41961" type="text/javascript"></script>
<script src="https://cdn02.jotfor.ms/js/payments/validategateways.js?v=3.3.41961" type="text/javascript"></script>
<script src="https://js.jotform.com/vendor/postMessage.js?3.3.41961" type="text/javascript"></script>
<script src="https://cdn01.jotfor.ms/s/umd/1405c44717b/for-widgets-server.js?v=3.3.41961" type="text/javascript"></script>
<script src="https://cdn03.jotfor.ms/js/vendor/math-processor.js?v=3.3.41961" type="text/javascript"></script>
<script type="text/javascript"> JotForm.newDefaultTheme = false;
    JotForm.extendsNewTheme = false;
    JotForm.singleProduct = true;
    JotForm.newPaymentUIForNewCreatedForms = false;

   JotForm.setCalculations([{"replaceText":"","readOnly":false,"newCalculationType":true,"useCommasForDecimals":false,"operands":"282","equation":"{282}","showBeforeInput":false,"showEmptyDecimals":false,"ignoreHiddenFields":false,"insertAsText":false,"id":"action_1597251557279","resultField":"281","decimalPlaces":"2","isError":false,"conditionId":"1597251582232","conditionTrue":false,"baseField":"261"}]);
   JotForm.setConditions([{"action":[{"id":"action_1614191298036","visibility":"Hide","isError":false,"field":"267"}],"id":"1614191319982","index":"0","link":"Any","priority":"0","terms":[{"id":"term_1614191298036","field":"267","operator":"equals","value":"Anuncio en Internet","isError":false}],"type":"field"},{"action":[{"id":"action_1624402605076","visibility":"Show","isError":false,"field":"310"},{"id":"action_0_1624402601361","visibility":"Show","isError":false,"field":"299"}],"id":"1605289776114","index":"2","link":"Any","priority":"2","terms":[{"id":"term_0_1624402601361","field":"129","operator":"equals","value":"Subsanación de Expediente","isError":false}],"type":"field"},{"action":[{"id":"action_1605288671747","visibility":"HideMultiple","isError":false,"fields":["20","38","117","41","108","109","126","124","59","120","121","122"]}],"id":"1605288838237","index":"3","link":"Any","priority":"3","terms":[{"id":"term_1605288671747","field":"129","operator":"equals","value":"Subsanación de Expediente","isError":false}],"type":"field"},{"action":[{"replaceText":"","readOnly":false,"newCalculationType":true,"useCommasForDecimals":false,"operands":"282","equation":"{282}","showBeforeInput":false,"showEmptyDecimals":false,"ignoreHiddenFields":false,"insertAsText":false,"id":"action_1597251557279","resultField":"281","decimalPlaces":"2","isError":false,"conditionId":"1597251582232","conditionTrue":false,"baseField":"261"}],"id":"1597251582232","index":"4","link":"Any","priority":"4","terms":[{"id":"term_1597251557279","field":"261","operator":"isFilled","value":"","isError":false}],"type":"calculation"},{"action":[{"id":"action_0_1607459157166","visibility":"HideMultiple","isError":false,"fields":["126","124","59","271","86"]},{"id":"action_1_1607459157166","visibility":"Show","isError":false,"field":"302"},{"id":"action_2_1607459157166","visibility":"Show","isError":false,"field":"301"},{"id":"action_3_1607459157166","visibility":"Show","isError":false,"field":"275"}],"id":"1590787248881","index":"5","link":"Any","priority":"5","terms":[{"id":"term_0_1607459157166","field":"129","operator":"equals","value":"Nacionalidad Italiana","isError":false}],"type":"field"},{"action":[{"id":"action_0_1587524390048","visibility":"Show","isError":false,"field":"272"}],"id":"1574970648452","index":"6","link":"Any","priority":"6","terms":[{"id":"term_0_1587524390048","field":"271","operator":"equals","value":"Si","isError":false}],"type":"field"},{"action":[{"id":"action_0_1603897715365","visibility":"ShowMultiple","isError":false,"fields":["263","285","286"]}],"id":"1564415064233","index":"7","link":"Any","priority":"7","terms":[{"id":"term_0_1603897715364","field":"262","operator":"equals","value":"Si","isError":false}],"type":"field"},{"action":[{"id":"action_0_1587524160202","visibility":"Show","isError":false,"field":"59"}],"id":"1561922145560","index":"8","link":"Any","priority":"8","terms":[{"id":"term_0_1587524160202","field":"126","operator":"equals","value":"Si","isError":false}],"type":"field"},{"action":[{"id":"action_1591656190422","visibility":"Show","isError":false,"field":"116"},{"id":"action_1591656178707","visibility":"Show","isError":false,"field":"115"},{"id":"action_1591656166699","visibility":"Show","isError":false,"field":"114"},{"id":"action_1591656156732","visibility":"Show","isError":false,"field":"125"},{"id":"action_0_1591656152695","visibility":"Show","isError":false,"field":"132"}],"id":"1561917310093","index":"9","link":"Any","priority":"9","terms":[{"id":"term_0_1591656152695","field":"119","operator":"equals","value":"No","isError":false}],"type":"field"},{"action":[{"id":"action_0_1597238462972","visibility":"Show","isError":false,"field":"20"}],"id":"1561916843301","index":"12","link":"All","priority":"12","terms":[{"id":"term_1597238469942","field":"111","operator":"equals","value":"FEMENINO \u002F FEMALE","isError":false},{"id":"term_0_1597238462972","field":"280","operator":"equals","value":"CASADO (A)","isError":false}],"type":"field"},{"action":[{"id":"action_0_1590787599214","visibility":"Show","isError":false,"field":"124"}],"id":"1557415510350","index":"13","link":"Any","priority":"13","terms":[{"id":"term_0_1590787599214","field":"126","operator":"equals","value":"Si","isError":false}],"type":"field"},{"action":[{"id":"action_0_1587524263352","visibility":"Show","isError":false,"field":"121"},{"id":"action_1_1587524263352","visibility":"Show","isError":false,"field":"122"}],"id":"1557355631498","index":"14","link":"Any","priority":"14","terms":[{"id":"term_0_1587524263352","field":"120","operator":"equals","value":"Si","isError":false}],"type":"field"},{"action":[{"id":"action_0_1557371983011","visibility":"Show","isError":false,"field":"125"},{"id":"action_1_1557371983011","visibility":"Show","isError":false,"field":"114"},{"id":"action_2_1557371983011","isError":false,"visibility":"Show","field":"115"},{"id":"action_3_1557371983011","visibility":"Show","isError":false,"field":"116"}],"id":"1557355054457","index":"15","link":"Any","priority":"15","terms":[{"id":"term_0_1557371983011","field":"119","operator":"equals","value":"No","isError":false}],"type":"field"},{"action":[{"id":"action_0_1598621048797","visibility":"Show","isError":false,"field":"38"},{"id":"action_1_1598621048797","visibility":"Show","isError":false,"field":"117"},{"id":"action_2_1598621048797","visibility":"Show","isError":false,"field":"41"}],"id":"1557348668017","index":"16","link":"Any","priority":"16","terms":[{"id":"term_0_1598621048797","field":"280","operator":"equals","value":"CASADO (A)","isError":false}],"type":"field"},{"action":[{"id":"action_0_1598621067251","visibility":"Show","isError":false,"field":"38"}],"id":"1598621059209","index":"17","link":"Any","priority":"17","terms":[{"id":"term_0_1598621067251","field":"280","operator":"equals","value":"DIVORCIADO (A)","isError":false}],"type":"field"},{"action":[{"id":"action_0_1598621125423","visibility":"Show","isError":false,"field":"38"}],"id":"1598621119468","index":"18","link":"Any","priority":"18","terms":[{"id":"term_0_1598621125423","field":"280","operator":"equals","value":"VIUDO (A)","isError":false}],"type":"field"}]);    JotForm.clearFieldOnHide="disable";
    JotForm.submitError="jumpToFirstError";

    JotForm.init(function(){
    /*INIT-START*/
if (window.JotForm && JotForm.accessible) $('input_67').setAttribute('tabindex',0);
if (window.JotForm && JotForm.accessible) $('input_68').setAttribute('tabindex',0);
if (window.JotForm && JotForm.accessible) $('input_329').setAttribute('tabindex',0);
if (window.JotForm && JotForm.accessible) $('input_330').setAttribute('tabindex',0);
      JotForm.alterTexts([""]);
      JotForm.alterTexts(undefined, true);
      FormTranslation.init({"detectUserLanguage":"1","firstPageOnly":"1","options":"Español|English (US)","originalLanguage":"es","primaryLanguage":"es","saveUserLanguage":"1","showStatus":"Flag & Text","theme":"light-theme","version":"2"});
    /*INIT-END*/
    });

   JotForm.prepareCalculationsOnTheFly([null,null,null,null,null,null,null,null,null,null,null,null,null,{"name":"terminarregistronbspynbspenviarnbspinformacion","qid":"13","text":"Enviar Firmado","type":"control_button"},null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,{"description":"","name":"nombres","qid":"67","subLabel":"COMO APARECEN EN EL PASAPORTE","text":"Nombres","type":"control_textbox"},{"description":"","name":"apellidos","qid":"68","subLabel":"COMO APARECEN EN EL PASAPORTE ","text":"Apellidos","type":"control_textbox"},null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,{"name":"clicPara84","qid":"84","text":"A continuacion, por favor lea el siguiente Acuerdo de Encargo Profesional y si esta de acuerdo por favor firme en la parte de abajo.\nLa firma es electronica y totalmente valida como una firma fisica, segun la Legislacion Europea.\nUsted recibira una copia de la firma y el acuerdo en el email introducido anteriormente.","type":"control_text"},null,{"name":"input86","qid":"86","text":"","type":"control_widget"},null,{"name":"dibujarDentro","qid":"88","text":"Dibujar dentro del recuadro sus iniciales con el raton o el dedo, para asi confirmar su aceptacion al acuerdo de encargo profesional y la licitud de los fondos.","type":"control_widget"},null,null,null,null,null,null,null,null,null,null,{"name":"divider99","qid":"99","type":"control_divider"},null,{"name":"divider","qid":"101","type":"control_divider"},null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,{"name":"input144","qid":"144","text":"","type":"control_text"},null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,{"name":"input278","qid":"278","text":"Licitud de los Fondos\nEl cliente declara que los fondos con los que realizara los pagos a SEFAR UNIVERSAL y demas derivados del presente Contrato, tienen un origen licito y por ende no provienen de actividades ilicitas o contrarias a la ley o buenas costumbres. El cliente exime a SEFAR UNIVERSAL, de toda responsabilidad civil, penal, o administrativa si la declaracion contenida en esta clausula fuere falsa.\nSirvase leer nuestra Politica de Privacidad","type":"control_text"},null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,{"name":"acuerdoDe","qid":"328","text":"Acuerdo de Encargo Profesional","type":"control_head"},{"description":"","name":"nro_pasaporte","qid":"329","subLabel":"","text":"Nro de Pasaporte","type":"control_textbox"},{"description":"","name":"nac_solicitada","qid":"330","subLabel":"","text":"Nacionalidad Solicitada","type":"control_textbox"}]);
   setTimeout(function() {
JotForm.paymentExtrasOnTheFly([null,null,null,null,null,null,null,null,null,null,null,null,null,{"name":"terminarregistronbspynbspenviarnbspinformacion","qid":"13","text":"Enviar Firmado","type":"control_button"},null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,{"description":"","name":"nombres","qid":"67","subLabel":"COMO APARECEN EN EL PASAPORTE","text":"Nombres","type":"control_textbox"},{"description":"","name":"apellidos","qid":"68","subLabel":"COMO APARECEN EN EL PASAPORTE ","text":"Apellidos","type":"control_textbox"},null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,{"name":"clicPara84","qid":"84","text":"A continuacion, por favor lea el siguiente Acuerdo de Encargo Profesional y si esta de acuerdo por favor firme en la parte de abajo.\nLa firma es electronica y totalmente valida como una firma fisica, segun la Legislacion Europea.\nUsted recibira una copia de la firma y el acuerdo en el email introducido anteriormente.","type":"control_text"},null,{"name":"input86","qid":"86","text":"","type":"control_widget"},null,{"name":"dibujarDentro","qid":"88","text":"Dibujar dentro del recuadro sus iniciales con el raton o el dedo, para asi confirmar su aceptacion al acuerdo de encargo profesional y la licitud de los fondos.","type":"control_widget"},null,null,null,null,null,null,null,null,null,null,{"name":"divider99","qid":"99","type":"control_divider"},null,{"name":"divider","qid":"101","type":"control_divider"},null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,{"name":"input144","qid":"144","text":"","type":"control_text"},null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,{"name":"input278","qid":"278","text":"Licitud de los Fondos\nEl cliente declara que los fondos con los que realizara los pagos a SEFAR UNIVERSAL y demas derivados del presente Contrato, tienen un origen licito y por ende no provienen de actividades ilicitas o contrarias a la ley o buenas costumbres. El cliente exime a SEFAR UNIVERSAL, de toda responsabilidad civil, penal, o administrativa si la declaracion contenida en esta clausula fuere falsa.\nSirvase leer nuestra Politica de Privacidad","type":"control_text"},null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,{"name":"acuerdoDe","qid":"328","text":"Acuerdo de Encargo Profesional","type":"control_head"},{"description":"","name":"nro_pasaporte","qid":"329","subLabel":"","text":"Nro de Pasaporte","type":"control_textbox"},{"description":"","name":"nac_solicitada","qid":"330","subLabel":"","text":"Nacionalidad Solicitada","type":"control_textbox"}]);}, 20); 
</script>
<link href="https://cdn01.jotfor.ms/static/formCss.css?3.3.41961" rel="stylesheet" type="text/css" />
<style type="text/css">@media print{.form-section{display:inline!important}.form-pagebreak{display:none!important}.form-section-closed{height:auto!important}.page-section{position:initial!important}}</style>
<link type="text/css" rel="stylesheet" href="https://cdn02.jotfor.ms/css/styles/nova.css?3.3.41961" />
<link type="text/css" rel="stylesheet" href="https://cdn03.jotfor.ms/themes/CSS/566a91c2977cdfcd478b4567.css?v=3.3.41961&themeRevisionID=5f6c4c83346ec05354558fe8"/>
<link type="text/css" rel="stylesheet" href="https://cdn01.jotfor.ms/css/styles/payment/payment_feature.css?3.3.41961" />
<style type="text/css">
    .form-label-left{
        width:130px;
    }
    .form-line{
        padding-top:12px;
        padding-bottom:12px;
    }
    .form-label-right{
        width:130px;
    }
    .form-all{
        width:900px;
        color:#555 !important;
        font-family:'Verdana';
        font-size:18px;
    }
    .form-radio-item label, .form-checkbox-item label, .form-grading-label, .form-header{
        color: false;
    }

</style>

<style type="text/css" id="form-designer-style">
@import (css) "@{buttonFontLink}";
    /* Injected CSS Code */
.form-all:after {
  content: "";
  display: table;
  clear: both;
}
.form-all {
  font-family: "Verdana", sans-serif;
}
.form-all {
  width: 860px;
}
.form-label-left,
.form-label-right {
  width: 130px;
}
.form-label {
  white-space: normal;
}
.form-label.form-label-auto {
  display: block;
  float: none;
  word-break: break-word;
  text-align: left;
}
.form-label-left {
  display: inline-block;
  white-space: normal;
  float: left;
  text-align: left;
}
.form-label-right {
  display: inline-block;
  white-space: normal;
  float: left;
  text-align: right;
}
.form-label-top {
  white-space: normal;
  display: block;
  float: none;
  text-align: left;
}
.form-radio-item label:before {
  top: 0;
}
.form-all {
  font-size: 16px;
}
.form-label {
  font-weight: normal;
  font-size: 0.95em;
}
.supernova {
  background-color: #ffffff;
}
.supernova body {
  background-color: transparent;
}
/*
@width30: (unit(@formWidth, px) + 60px);
@width60: (unit(@formWidth, px)+ 120px);
@width90: (unit(@formWidth, px)+ 180px);
*/
/* | */
@media screen and (min-width: 480px) {
  .supernova .form-all {
    border: 1px solid #e6e6e6;
    box-shadow: 0 3px 9px rgba(0, 0, 0, 0.1);
  }
}
/* | */
/* | */
@media screen and (max-width: 480px) {
  .jotform-form .form-all {
    margin: 0;
    width: 100%;
  }
}
/* | */
/* | */
@media screen and (min-width: 480px) and (max-width: 767px) {
  .jotform-form .form-all {
    margin: 0;
    width: 100%;
  }
}
/* | */
/* | */
@media screen and (min-width: 480px) and (max-width: 859px) {
  .jotform-form .form-all {
    margin: 0;
    width: 100%;
  }
}
/* | */
/* | */
@media screen and (min-width: 768px) {
  .jotform-form {
    padding: 60px 0;
  }
}
/* | */
/* | */
@media screen and (max-width: 859px) {
  .jotform-form .form-all {
    margin: 0;
    width: 100%;
  }
}
/* | */
.supernova .form-all,
.form-all {
  background-color: #ffffff;
  border: 1px solid transparent;
}
.form-header-group {
  border-color: #e6e6e6;
}
.form-matrix-table tr {
  border-color: #e6e6e6;
}
.form-matrix-table tr:nth-child(2n) {
  background-color: #f2f2f2;
}
.form-all {
  color: #555555;
}
.form-header-group .form-header {
  color: #555555;
}
.form-header-group .form-subHeader {
  color: #6f6f6f;
}
.form-sub-label {
  color: #6f6f6f;
}
.form-label-top,
.form-label-left,
.form-label-right,
.form-html {
  color: #6f6f6f;
}
.form-checkbox-item label,
.form-radio-item label {
  color: #555555;
}
.form-line.form-line-active {
  -webkit-transition-property: all;
  -moz-transition-property: all;
  -ms-transition-property: all;
  -o-transition-property: all;
  transition-property: all;
  -webkit-transition-duration: 0.3s;
  -moz-transition-duration: 0.3s;
  -ms-transition-duration: 0.3s;
  -o-transition-duration: 0.3s;
  transition-duration: 0.3s;
  -webkit-transition-timing-function: ease;
  -moz-transition-timing-function: ease;
  -ms-transition-timing-function: ease;
  -o-transition-timing-function: ease;
  transition-timing-function: ease;
  background-color: #ffffe0;
}
/* omer */
.form-radio-item,
.form-checkbox-item {
  padding-bottom: 0px !important;
}
.form-radio-item:last-child,
.form-checkbox-item:last-child {
  padding-bottom: 0;
}
/* omer */
[data-type="control_radio"] .form-input,
[data-type="control_checkbox"] .form-input,
[data-type="control_radio"] .form-input-wide,
[data-type="control_checkbox"] .form-input-wide {
  width: 100%;
  max-width: 348px;
}
.form-radio-item,
.form-checkbox-item {
  width: 100%;
  max-width: 348px;
  box-sizing: border-box;
}
.form-textbox.form-radio-other-input,
.form-textbox.form-checkbox-other-input {
  width: 80%;
  margin-left: 3%;
  box-sizing: border-box;
}
.form-multiple-column {
  width: 100%;
}
.form-multiple-column .form-radio-item,
.form-multiple-column .form-checkbox-item {
  width: 10%;
}
.form-multiple-column[data-columncount="1"] .form-radio-item,
.form-multiple-column[data-columncount="1"] .form-checkbox-item {
  width: 100%;
}
.form-multiple-column[data-columncount="2"] .form-radio-item,
.form-multiple-column[data-columncount="2"] .form-checkbox-item {
  width: 50%;
}
.form-multiple-column[data-columncount="3"] .form-radio-item,
.form-multiple-column[data-columncount="3"] .form-checkbox-item {
  width: 33.33333333%;
}
.form-multiple-column[data-columncount="4"] .form-radio-item,
.form-multiple-column[data-columncount="4"] .form-checkbox-item {
  width: 25%;
}
.form-multiple-column[data-columncount="5"] .form-radio-item,
.form-multiple-column[data-columncount="5"] .form-checkbox-item {
  width: 20%;
}
.form-multiple-column[data-columncount="6"] .form-radio-item,
.form-multiple-column[data-columncount="6"] .form-checkbox-item {
  width: 16.66666667%;
}
.form-multiple-column[data-columncount="7"] .form-radio-item,
.form-multiple-column[data-columncount="7"] .form-checkbox-item {
  width: 14.28571429%;
}
.form-multiple-column[data-columncount="8"] .form-radio-item,
.form-multiple-column[data-columncount="8"] .form-checkbox-item {
  width: 12.5%;
}
.form-multiple-column[data-columncount="9"] .form-radio-item,
.form-multiple-column[data-columncount="9"] .form-checkbox-item {
  width: 11.11111111%;
}
.form-single-column .form-checkbox-item,
.form-single-column .form-radio-item {
  width: 100%;
}
.form-checkbox-item .editor-container div,
.form-radio-item .editor-container div {
  position: relative;
}
.form-checkbox-item .editor-container div:before,
.form-radio-item .editor-container div:before {
  display: inline-block;
  vertical-align: middle;
  box-sizing: border-box;
  left: 0;
  width: 18px;
  height: 18px;
}
.form-checkbox-item,
.form-radio-item {
  padding-left: 2px;
}
.form-checkbox-item input,
.form-radio-item input {
  margin-top: 2px;
}
.supernova {
  height: 100%;
  background-repeat: no-repeat;
  background-attachment: scroll;
  background-position: center top;
  background-repeat: repeat;
}
.supernova {
  background-image: none;
}
#stage {
  background-image: none;
}
/* | */
.form-all {
  background-repeat: no-repeat;
  background-attachment: scroll;
  background-position: center top;
  background-repeat: repeat;
}
.form-header-group {
  background-repeat: no-repeat;
  background-attachment: scroll;
  background-position: center top;
}
.form-line {
  margin-top: 12px;
  margin-bottom: 12px;
}
.form-line {
  padding: 12px 36px;
}
.form-all {
  border-radius: 20px;
}
.form-section:first-child {
  border-radius: 20px 20px 0 0;
}
.form-section:last-child {
  border-radius: 0 0 20px 20px;
}
.form-all .qq-upload-button,
.form-all .form-submit-button,
.form-all .form-submit-reset,
.form-all .form-submit-print {
  font-size: 1em;
  padding: 9px 15px;
  font-family: "Verdana", sans-serif;
  font-size: 16px;
  font-weight: normal;
}
.form-all .form-pagebreak-back,
.form-all .form-pagebreak-next {
  font-size: 1em;
  padding: 9px 15px;
  font-family: "Verdana", sans-serif;
  font-size: 16px;
  font-weight: normal;
}
/*
& when ( @buttonFontType = google ) {
    
}
*/
h2.form-header {
  line-height: 1.618em;
  font-size: 1.714em;
}
h2 ~ .form-subHeader {
  line-height: 1.5em;
  font-size: 1.071em;
}
.form-header-group {
  text-align: left;
}
.form-line {
  zoom: 1;
}
.form-line:before,
.form-line:after {
  display: table;
  content: '';
  line-height: 0;
}
.form-line:after {
  clear: both;
}
.form-captcha input,
.form-spinner input {
  width: 348px;
}
.form-textbox,
.form-textarea {
  width: 100%;
  max-width: 348px;
  box-sizing: border-box;
}
.form-input,
.form-address-table,
.form-matrix-table {
  width: 100%;
  max-width: 348px;
}
.form-radio-item,
.form-checkbox-item {
  width: 100%;
  max-width: 348px;
  box-sizing: border-box;
}
.form-textbox.form-radio-other-input,
.form-textbox.form-checkbox-other-input {
  width: 80%;
  margin-left: 3%;
  box-sizing: border-box;
}
.form-multiple-column {
  width: 100%;
}
.form-multiple-column .form-radio-item,
.form-multiple-column .form-checkbox-item {
  width: 10%;
}
.form-multiple-column[data-columncount="1"] .form-radio-item,
.form-multiple-column[data-columncount="1"] .form-checkbox-item {
  width: 100%;
}
.form-multiple-column[data-columncount="2"] .form-radio-item,
.form-multiple-column[data-columncount="2"] .form-checkbox-item {
  width: 50%;
}
.form-multiple-column[data-columncount="3"] .form-radio-item,
.form-multiple-column[data-columncount="3"] .form-checkbox-item {
  width: 33.33333333%;
}
.form-multiple-column[data-columncount="4"] .form-radio-item,
.form-multiple-column[data-columncount="4"] .form-checkbox-item {
  width: 25%;
}
.form-multiple-column[data-columncount="5"] .form-radio-item,
.form-multiple-column[data-columncount="5"] .form-checkbox-item {
  width: 20%;
}
.form-multiple-column[data-columncount="6"] .form-radio-item,
.form-multiple-column[data-columncount="6"] .form-checkbox-item {
  width: 16.66666667%;
}
.form-multiple-column[data-columncount="7"] .form-radio-item,
.form-multiple-column[data-columncount="7"] .form-checkbox-item {
  width: 14.28571429%;
}
.form-multiple-column[data-columncount="8"] .form-radio-item,
.form-multiple-column[data-columncount="8"] .form-checkbox-item {
  width: 12.5%;
}
.form-multiple-column[data-columncount="9"] .form-radio-item,
.form-multiple-column[data-columncount="9"] .form-checkbox-item {
  width: 11.11111111%;
}
[data-type="control_dropdown"] .form-dropdown {
  width: 100% !important;
  max-width: 348px;
}
[data-type="control_fullname"] .form-sub-label-container {
  box-sizing: border-box;
  width: 48%;
}
[data-type="control_fullname"] .form-sub-label-container:first-child {
  margin-right: 4%;
}
[data-type="control_phone"] .form-sub-label-container {
  width: 62.5%;
  margin-left: 2.5%;
  margin-right: 0;
  float: left;
  position: relative;
}
[data-type="control_phone"] .form-sub-label-container:first-child {
  width: 32.5%;
  margin-right: 2.5%;
  margin-left: 0;
}
[data-type="control_phone"] .form-sub-label-container:first-child [data-component=areaCode] {
  width: 93%;
  float: left;
}
[data-type="control_phone"] .form-sub-label-container:first-child [data-component=areaCode] ~ .form-sub-label {
  display: inline-block;
}
[data-type="control_phone"] .form-sub-label-container:first-child .phone-separate {
  position: absolute;
  top: 0;
  right: -16%;
  width: 24%;
  text-align: center;
  text-indent: -4px;
}
[data-type="control_birthdate"] .form-sub-label-container {
  width: 22%;
  margin-right: 3%;
}
[data-type="control_birthdate"] .form-sub-label-container:first-child {
  width: 50%;
}
[data-type="control_birthdate"] .form-sub-label-container:last-child {
  margin-right: 0;
}
[data-type="control_birthdate"] .form-sub-label-container .form-dropdown {
  width: 100%;
}
[data-type="control_payment"] .form-sub-label-container {
  width: auto;
}
[data-type="control_payment"] .form-sub-label-container .form-dropdown {
  width: 100%;
}
.form-address-table td .form-dropdown {
  width: 100%;
}
.form-address-table td .form-sub-label-container {
  width: 96%;
}
.form-address-table td:last-child .form-sub-label-container {
  margin-left: 4%;
}
.form-address-table td[colspan="2"] .form-sub-label-container {
  width: 100%;
  margin: 0;
}
/*.form-dropdown,
.form-radio-item,
.form-checkbox-item,
.form-radio-other-input,
.form-checkbox-other-input,*/
.form-captcha input,
.form-spinner input,
.form-error-message {
  padding: 4px 3px 2px 3px;
}
.form-header-group {
  font-family: "Verdana", sans-serif;
}
.form-section {
  padding: 0px 0px 0px 0px;
}
.form-header-group {
  margin: 12px 36px 12px 36px;
}
.form-header-group {
  padding: 24px 0px 24px 0px;
}
.form-textbox,
.form-textarea {
  padding: 4px 3px 2px 3px;
}
.form-textbox,
.form-textarea {
  width: 100%;
  max-width: 348px;
  box-sizing: border-box;
}
[data-type="control_textbox"] .form-input,
[data-type="control_textarea"] .form-input,
[data-type="control_fullname"] .form-input,
[data-type="control_phone"] .form-input,
[data-type="control_datetime"] .form-input,
[data-type="control_address"] .form-input,
[data-type="control_email"] .form-input,
[data-type="control_passwordbox"] .form-input,
[data-type="control_autocomp"] .form-input,
[data-type="control_textbox"] .form-input-wide,
[data-type="control_textarea"] .form-input-wide,
[data-type="control_fullname"] .form-input-wide,
[data-type="control_phone"] .form-input-wide,
[data-type="control_datetime"] .form-input-wide,
[data-type="control_address"] .form-input-wide,
[data-type="control_email"] .form-input-wide,
[data-type="control_passwordbox"] .form-input-wide,
[data-type="control_autocomp"] .form-input-wide {
  width: 100%;
  max-width: 348px;
}
[data-type="control_fullname"] .form-sub-label-container {
  box-sizing: border-box;
  width: 48%;
  margin-right: 0;
  float: left;
}
[data-type="control_fullname"] .form-sub-label-container:first-child {
  margin-right: 4%;
}
[data-type="control_phone"] .form-sub-label-container {
  width: 62.5%;
  margin-left: 2.5%;
  margin-right: 0;
  float: left;
  position: relative;
}
[data-type="control_phone"] .form-sub-label-container:first-child {
  width: 32.5%;
  margin-right: 2.5%;
  margin-left: 0;
}
[data-type="control_phone"] .form-sub-label-container:first-child [data-component=areaCode] {
  width: 93%;
  float: left;
}
[data-type="control_phone"] .form-sub-label-container:first-child [data-component=areaCode] ~ .form-sub-label {
  display: inline-block;
}
[data-type="control_phone"] .form-sub-label-container:first-child .phone-separate {
  position: absolute;
  top: 0;
  right: -16%;
  width: 24%;
  text-align: center;
  text-indent: -4px;
}
[data-type="control_phone"] .form-sub-label-container .date-separate {
  visibility: hidden;
}
.form-matrix-table {
  width: 100%;
  max-width: 348px;
}
.form-address-table {
  width: 100%;
  max-width: 348px;
}
.form-address-table td .form-dropdown {
  width: 100%;
}
.form-address-table td .form-sub-label-container {
  width: 96%;
}
.form-address-table td:last-child .form-sub-label-container {
  margin-left: 4%;
}
.form-address-table td[colspan="2"] .form-sub-label-container {
  width: 100%;
  margin: 0;
}
.form-matrix-row-headers,
.form-matrix-column-headers,
.form-matrix-values {
  padding: 4px;
}
[data-type="control_dropdown"] .form-input,
[data-type="control_birthdate"] .form-input,
[data-type="control_time"] .form-input,
[data-type="control_dropdown"] .form-input-wide,
[data-type="control_birthdate"] .form-input-wide,
[data-type="control_time"] .form-input-wide {
  width: 100%;
  max-width: 348px;
}
[data-type="control_dropdown"] .form-dropdown {
  width: 100% !important;
  max-width: 348px;
}
[data-type="control_birthdate"] .form-sub-label-container {
  width: 22%;
  margin-right: 3%;
}
[data-type="control_birthdate"] .form-sub-label-container:first-child {
  width: 50%;
}
[data-type="control_birthdate"] .form-sub-label-container:last-child {
  margin-right: 0;
}
[data-type="control_birthdate"] .form-sub-label-container .form-dropdown {
  width: 100%;
}
.form-label {
  font-family: "Verdana", sans-serif;
}
li[data-type="control_image"] div {
  text-align: left;
}
li[data-type="control_image"] img {
  border: none;
  border-width: 0px !important;
  border-style: solid !important;
  border-color: false !important;
}
.form-line-column {
  width: auto;
}
.form-line-error {
  overflow: hidden;
  -webkit-transition-property: none;
  -moz-transition-property: none;
  -ms-transition-property: none;
  -o-transition-property: none;
  transition-property: none;
  -webkit-transition-duration: 0.3s;
  -moz-transition-duration: 0.3s;
  -ms-transition-duration: 0.3s;
  -o-transition-duration: 0.3s;
  transition-duration: 0.3s;
  -webkit-transition-timing-function: ease;
  -moz-transition-timing-function: ease;
  -ms-transition-timing-function: ease;
  -o-transition-timing-function: ease;
  transition-timing-function: ease;
  background-color: #fff4f4;
}
.form-line-error .form-error-message {
  background-color: #ff3200;
  clear: both;
  float: none;
}
.form-line-error .form-error-message .form-error-arrow {
  border-bottom-color: #ff3200;
}
.form-line-error input:not(#coupon-input),
.form-line-error textarea,
.form-line-error .form-validation-error {
  border: 1px solid #ff3200;
  box-shadow: 0 0 3px #ff3200;
}
.ie-8 .form-all {
  margin-top: auto;
  margin-top: initial;
}
.ie-8 .form-all:before {
  display: none;
}
[data-type="control_clear"] {
  display: none;
}
/* | */
@media screen and (max-width: 480px), screen and (max-device-width: 767px) and (orientation: portrait), screen and (max-device-width: 415px) and (orientation: landscape) {
  .testOne {
    letter-spacing: 0;
  }
  .form-all {
    border: 0;
    max-width: initial;
  }
  .form-sub-label-container {
    width: 100%;
    margin: 0;
    margin-right: 0;
    float: left;
    box-sizing: border-box;
  }
  span.form-sub-label-container + span.form-sub-label-container {
    margin-right: 0;
  }
  .form-sub-label {
    white-space: normal;
  }
  .form-address-table td,
  .form-address-table th {
    padding: 0 1px 10px;
  }
  .form-submit-button,
  .form-submit-print,
  .form-submit-reset {
    width: 100%;
    margin-left: 0!important;
  }
  div[id*=at_] {
    font-size: 14px;
    font-weight: 700;
    height: 8px;
    margin-top: 6px;
  }
  .showAutoCalendar {
    width: 20px;
  }
  img.form-image {
    max-width: 100%;
    height: auto;
  }
  .form-matrix-row-headers {
    width: 100%;
    word-break: break-all;
    min-width: 80px;
  }
  .form-collapse-table,
  .form-header-group {
    margin: 0;
  }
  .form-collapse-table {
    height: 100%;
    display: inline-block;
    width: 100%;
  }
  .form-collapse-hidden {
    display: none !important;
  }
  .form-input {
    width: 100%;
  }
  .form-label {
    width: 100% !important;
  }
  .form-label-left,
  .form-label-right {
    display: block;
    float: none;
    text-align: left;
    width: auto!important;
  }
  .form-line,
  .form-line.form-line-column {
    padding: 2% 5%;
    box-sizing: border-box;
  }
  input[type=text],
  input[type=email],
  input[type=tel],
  textarea {
    width: 100%;
    box-sizing: border-box;
    max-width: initial !important;
  }
  .form-radio-other-input,
  .form-checkbox-other-input {
    max-width: 55% !important;
  }
  .form-dropdown,
  .form-textarea,
  .form-textbox {
    width: 100%!important;
    box-sizing: border-box;
  }
  .form-input,
  .form-input-wide,
  .form-textarea,
  .form-textbox,
  .form-dropdown {
    max-width: initial!important;
  }
  .form-checkbox-item:not(#foo),
  .form-radio-item:not(#foo) {
    width: 100%;
  }
  .form-address-city,
  .form-address-line,
  .form-address-postal,
  .form-address-state,
  .form-address-table,
  .form-address-table .form-sub-label-container,
  .form-address-table select,
  .form-input {
    width: 100%;
  }
  div.form-header-group {
    padding: 24px 0px !important;
    margin: 0 12px 2% !important;
    margin-left: 5%!important;
    margin-right: 5%!important;
    box-sizing: border-box;
  }
  div.form-header-group.hasImage img {
    max-width: 100%;
  }
  [data-type="control_button"] {
    margin-bottom: 0 !important;
  }
  [data-type=control_fullname] .form-sub-label-container {
    width: 48%;
  }
  [data-type=control_fullname] .form-sub-label-container:first-child {
    margin-right: 4%;
  }
  [data-type=control_phone] .form-sub-label-container {
    width: 65%;
    margin-right: 0;
    margin-left: 0;
    float: left;
  }
  [data-type=control_phone] .form-sub-label-container:first-child {
    width: 31%;
    margin-right: 4%;
  }
  [data-type=control_datetime] .allowTime-container {
    width: 100%;
  }
  [data-type=control_datetime] .allowTime-container .form-sub-label-container {
    width: 24%!important;
    margin-left: 6%;
    margin-right: 0;
  }
  [data-type=control_datetime] .allowTime-container .form-sub-label-container:first-child {
    margin-left: 0;
  }
  [data-type=control_datetime] span + span + span > span:first-child {
    display: block;
    width: 100% !important;
  }
  [data-type=control_birthdate] .form-sub-label-container,
  [data-type=control_time] .form-sub-label-container {
    width: 27.3%!important;
    margin-right: 6% !important;
  }
  [data-type=control_time] .form-sub-label-container:last-child {
    width: 33.3%!important;
    margin-right: 0 !important;
  }
  .form-pagebreak-back-container,
  .form-pagebreak-next-container {
    min-height: 1px;
    width: 50% !important;
  }
  .form-pagebreak-back,
  .form-pagebreak-next,
  .form-product-item.hover-product-item {
    width: 100%;
  }
  .form-pagebreak-back-container {
    padding: 0;
    text-align: right;
  }
  .form-pagebreak-next-container {
    padding: 0;
    text-align: left;
  }
  .form-pagebreak {
    margin: 0 auto;
  }
  .form-buttons-wrapper {
    margin: 0!important;
    margin-left: 0!important;
  }
  .form-buttons-wrapper button {
    width: 100%;
  }
  .form-buttons-wrapper .form-submit-print {
    margin: 0 !important;
  }
  table {
    width: 100%!important;
    max-width: initial!important;
  }
  table td + td {
    padding-left: 3%;
  }
  .form-checkbox-item,
  .form-radio-item {
    white-space: normal!important;
  }
  .form-checkbox-item input,
  .form-radio-item input {
    width: auto;
  }
  .form-collapse-table {
    margin: 0 5%;
    display: block;
    zoom: 1;
    width: auto;
  }
  .form-collapse-table:before,
  .form-collapse-table:after {
    display: table;
    content: '';
    line-height: 0;
  }
  .form-collapse-table:after {
    clear: both;
  }
  .fb-like-box {
    width: 98% !important;
  }
  .form-error-message {
    clear: both;
    bottom: -10px;
  }
  .date-separate,
  .phone-separate {
    display: none;
  }
  .custom-field-frame,
  .direct-embed-widgets,
  .signature-pad-wrapper {
    width: 100% !important;
  }
}
/* | */

/*PREFERENCES STYLE*/
    .form-all {
      font-family: Verdana, sans-serif;
    }
    .form-all .qq-upload-button,
    .form-all .form-submit-button,
    .form-all .form-submit-reset,
    .form-all .form-submit-print {
      font-family: Verdana, sans-serif;
    }
    .form-all .form-pagebreak-back-container,
    .form-all .form-pagebreak-next-container {
      font-family: Verdana, sans-serif;
    }
    .form-header-group {
      font-family: Verdana, sans-serif;
    }
    .form-label {
      font-family: Verdana, sans-serif;
    }
  
    .form-label.form-label-auto {
      
    display: block;
    float: none;
    text-align: left;
    width: 100%;
  
    }
  
    .form-line {
      margin-top: px;
      margin-bottom: px;
    }
  
    .form-all {
      max-width: 900px;
      width: 100%;
    }
  
    .form-label.form-label-left,
    .form-label.form-label-right,
    .form-label.form-label-left.form-label-auto,
    .form-label.form-label-right.form-label-auto {
      width: 130px;
    }
  
    .form-all {
      font-size: 18px
    }
    .form-all .qq-upload-button,
    .form-all .qq-upload-button,
    .form-all .form-submit-button,
    .form-all .form-submit-reset,
    .form-all .form-submit-print {
      font-size: 18px
    }
    .form-all .form-pagebreak-back-container,
    .form-all .form-pagebreak-next-container {
      font-size: 18px
    }
  
    .supernova .form-all, .form-all {
      background-color: #fff;
    }
  
    .form-all {
      color: #555;
    }
    .form-header-group .form-header {
      color: #555;
    }
    .form-header-group .form-subHeader {
      color: #555;
    }
    .form-label-top,
    .form-label-left,
    .form-label-right,
    .form-html,
    .form-checkbox-item label,
    .form-radio-item label {
      color: #555;
    }
    .form-sub-label {
      color: #6f6f6f;
    }
  
    .supernova {
      background-color: undefined;
    }
    .supernova body {
      background: transparent;
    }
  
    .form-textbox,
    .form-textarea,
    .form-dropdown,
    .form-radio-other-input,
    .form-checkbox-other-input,
    .form-captcha input,
    .form-spinner input {
      background-color: undefined;
    }
  
    .supernova {
      background-image: none;
    }
    #stage {
      background-image: none;
    }
  
    .form-all {
      background-image: none;
    }
  
  .ie-8 .form-all:before { display: none; }
  .ie-8 {
    margin-top: auto;
    margin-top: initial;
  }
  
  /*PREFERENCES STYLE*//*__INSPECT_SEPERATOR__*/
.form-label.form-label-auto {
    display : block;
    float : none;
    text-align : left;
    width : 100%;
}


    /* Injected CSS Code */
</style>

<link type="text/css" rel="stylesheet" href="https://cdn02.jotfor.ms/css/styles/buttons/form-submit-button-light_rounded.css?3.3.41961"/>
<form class="jotform-form" action="https://eu-submit.jotform.com/submit/231384136753659" method="post" name="form_231384136753659" id="231384136753659" accept-charset="utf-8" autocomplete="on"><input type="hidden" name="formID" value="231384136753659" /><input type="hidden" id="JWTContainer" value="" /><input type="hidden" id="cardinalOrderNumber" value="" />
  <div role="main" class="form-all">
    <link type="text/css" rel="stylesheet" media="all" href="https://cdn.jotfor.ms/wizards/languageWizard/custom-dropdown/css/lang-dd.css?3.3.41961" />
    <div class="cont"><input type="text" id="input_language" name="input_language" style="display:none" />
      <div class="language-dd" id="langDd" style="display:none">
        <div class="dd-placeholder lang-emp">Language</div>
        <ul class="lang-list dn" id="langList">
          <li data-lang="es" class="es">Español</li>
          <li data-lang="en" class="en">English (US)</li>
        </ul>
      </div>
    </div>
    <script type="text/javascript" src="https://cdn.jotfor.ms/js/formTranslation.v2.js?3.3.41961"></script>
    <ul class="form-section page-section">
      <li id="cid_328" class="form-input-wide" data-type="control_head">
        <div class="form-header-group  header-large">
          <div class="header-text httac htvam">
            <h1 id="header_328" class="form-header" data-component="header">Acuerdo de Encargo Profesional</h1>
          </div>
        </div>
      </li>
      <li class="form-line form-line-column form-col-1 jf-required" data-type="control_textbox" id="id_67"><label class="form-label form-label-top" id="label_67" for="input_67"> Nombres<span class="form-required">*</span> </label>
        <div id="cid_67" class="form-input-wide jf-required"> <span class="form-sub-label-container" style="vertical-align:top"><input type="text" id="input_67" name="q67_nombres" data-type="input-textbox" class="form-textbox validate[required]" data-defaultvalue="" size="35" value="" data-component="textbox" aria-labelledby="label_67 sublabel_input_67" required="" /><label class="form-sub-label" for="input_67" id="sublabel_input_67" style="min-height:13px" aria-hidden="false">COMO APARECEN EN EL PASAPORTE</label></span> </div>
      </li>
      <li class="form-line form-line-column form-col-2 jf-required" data-type="control_textbox" id="id_68"><label class="form-label form-label-top" id="label_68" for="input_68"> Apellidos<span class="form-required">*</span> </label>
        <div id="cid_68" class="form-input-wide jf-required"> <span class="form-sub-label-container" style="vertical-align:top"><input type="text" id="input_68" name="q68_apellidos" data-type="input-textbox" class="form-textbox validate[required]" data-defaultvalue="" size="35" value="" data-component="textbox" aria-labelledby="label_68 sublabel_input_68" required="" /><label class="form-sub-label" for="input_68" id="sublabel_input_68" style="min-height:13px" aria-hidden="false">COMO APARECEN EN EL PASAPORTE </label></span> </div>
      </li>
      <li class="form-line form-line-column form-col-3 always-hidden" data-type="control_textbox" id="id_329"><label class="form-label form-label-top" id="label_329" for="input_329"> Nro de Pasaporte </label>
        <div id="cid_329" class="form-input-wide always-hidden"> <input type="text" id="input_329" name="q329_nro_pasaporte" data-type="input-textbox" class="form-textbox" data-defaultvalue="" size="20" value="" data-component="textbox" aria-labelledby="label_329" /> </div>
      </li>
      <li class="form-line form-line-column form-col-4 always-hidden" data-type="control_textbox" id="id_330"><label class="form-label form-label-top" id="label_330" for="input_330"> Nacionalidad Solicitada </label>
        <div id="cid_330" class="form-input-wide always-hidden"> <input type="text" id="input_330" name="q330_nac_solicitada" data-type="input-textbox" class="form-textbox" data-defaultvalue="" size="20" value="" data-component="textbox" aria-labelledby="label_330" /> </div>
      </li>
      <li class="form-line" data-type="control_divider" id="id_99">
        <div id="cid_99" class="form-input-wide">
          <div class="divider" data-component="divider" style="border-bottom-width:1px;border-bottom-style:solid;border-color:#FFFFFF;height:1px;margin-left:0px;margin-right:0px;margin-top:5px;margin-bottom:5px"></div>
        </div>
      </li>
      <li class="form-line form-line-column form-col-1" data-type="control_text" id="id_144">
        <div id="cid_144" class="form-input-wide">
          <div id="text_144" class="form-html" data-component="text" tabindex="0"></div>
        </div>
      </li>
      <li class="form-line" data-type="control_text" id="id_84">
        <div id="cid_84" class="form-input-wide">
          <div id="text_84" class="form-html" data-component="text" tabindex="0">
            <p style="text-align: center;"><span style="font-size: 12pt;">A continuación, por favor lea el siguiente Acuerdo de Encargo Profesional y si está de acuerdo por favor firme en la parte de abajo.</span></p>
            <p style="text-align: center;"><span style="font-size: 12pt;">La firma es electrónica y totalmente válida como una firma física, según la Legislación Europea.</span></p>
            <p style="text-align: center;"><span style="font-size: 12pt;">Usted recibirá una copia de la firma y el acuerdo en el email introducido anteriormente.</span></p>
          </div>
        </div>
      </li>
      <li class="form-line form-field-hidden" style="display:none;" data-type="control_widget" id="id_86">
        <div id="cid_86" class="form-input-wide">
          <div data-widget-name="PDF Embedder" style="width:100%;text-align:Center;overflow-x:auto" data-component="widget-field"><iframe data-client-id="529641beb15ce2ac76000007" title="PDF Embedder" frameBorder="0" scrolling="no" allowtransparency="true" allow="geolocation; microphone; camera; autoplay; encrypted-media; fullscreen" data-type="iframe" class="custom-field-frame" id="customFieldFrame_86" src="" style="max-width:500px;border:none;width:100%;height:400px" data-width="500" data-height="400"></iframe>
            <div class="widget-inputs-wrapper"><input type="hidden" id="input_86" class="form-hidden form-widget  " name="q86_input86" value="" /><input type="hidden" id="widget_settings_86" class="form-hidden form-widget-settings" value="%5B%7B%22name%22%3A%22link%22%2C%22value%22%3A%7B%22name%22%3A%22CONTRATO%20INICIAL%20SEFAR%20-%20El%20CLIENTE%20MODELO%20TODOS%20LOS%20SERVICIOS%20DEFI.pdf%22%2C%22type%22%3A%22application%2Fpdf%22%2C%22size%22%3A136646%2C%22url%22%3A%22www.jotform.com%22%2C%22base%22%3A%22CONTRATO%2520INICIAL%2520SEFAR%2520-%2520El%2520CLIENTE%2520MODELO%2520TODOS%2520LOS%2520SERVICIOS%2520DEFI.6256eb47a14964.95445768.pdf%22%2C%22path%22%3A%22%2Fuploads%2FSfarVzla%2Fform_files%2FCONTRATO%2520INICIAL%2520SEFAR%2520-%2520El%2520CLIENTE%2520MODELO%2520TODOS%2520LOS%2520SERVICIOS%2520DEFI.6256eb47a14964.95445768.pdf%22%2C%22owner%22%3A%22SfarVzla%22%7D%7D%2C%7B%22name%22%3A%22attach%22%2C%22value%22%3A%220%22%7D%5D" data-version="2" /></div>
            <script type="text/javascript">
              setTimeout(function()
              {
                var _cFieldFrame = document.getElementById("customFieldFrame_86");
                if (_cFieldFrame)
                {
                  _cFieldFrame.onload = function()
                  {
                    if (typeof widgetFrameLoaded !== 'undefined')
                    {
                      widgetFrameLoaded(86,
                      {
                        "formID": 231384136753659
                      })
                    }
                  };
                  _cFieldFrame.src = "//widgets.jotform.io/pdfEmbed/?qid=86&ref=" +
                    encodeURIComponent(window.location.protocol + "//" + window.location.host) + '' + '' + '' +
                    '&injectCSS=' + encodeURIComponent(window.location.search.indexOf("ndt=1") > -1);
                  _cFieldFrame.addClassName("custom-field-frame-rendered");
                }
              }, 0);
            </script>
          </div>
        </div>
      </li>
      <li class="form-line" data-type="control_divider" id="id_101">
        <div id="cid_101" class="form-input-wide">
          <div class="divider" data-component="divider" style="border-bottom-width:1px;border-bottom-style:solid;border-color:#FFFFFF;height:1px;margin-left:0px;margin-right:0px;margin-top:5px;margin-bottom:5px"></div>
        </div>
      </li>
      <li class="form-line" data-type="control_text" id="id_278">
        <div id="cid_278" class="form-input-wide">
          <div id="text_278" class="form-html" data-component="text" tabindex="0">
            <p style="text-align: center;"><span style="font-size: 14pt;">Licitud de los Fondos</span></p>
            <p style="text-align: center;">El cliente declara que los fondos con los que realizará los pagos a SEFAR UNIVERSAL y demás derivados del presente Contrato, tienen un origen lícito y por ende no provienen de actividades ilícitas o contrarias a la ley o buenas costumbres. El cliente exime a SEFAR UNIVERSAL, de toda responsabilidad civil, penal, o administrativa si la declaración contenida en esta cláusula fuere falsa.</p>
            <p style="text-align: center;">Sírvase leer nuestra <a title="Clic para abrir el documento" href="https://www.sefaruniversal.com/politica-de-privacidad/" target="_blank" rel="nofollow">Política de Privacidad</a></p>
          </div>
        </div>
      </li>
      <li class="form-line jf-required" data-type="control_widget" id="id_88"><label class="form-label form-label-top" id="label_88" for="input_88"> Dibujar dentro del recuadro sus iniciales con el ratón o el dedo, para así confirmar su aceptación al acuerdo de encargo profesional y la licitud de los fondos.<span class="form-required">*</span> </label>
        <div id="cid_88" class="form-input-wide jf-required">
          <div data-widget-name="Initials" style="width:100%;text-align:Center;overflow-x:auto" data-component="widget-field"><iframe data-client-id="533a8c19a3f5fec35d00009a" title="Initials" frameBorder="0" scrolling="no" allowtransparency="true" allow="geolocation; microphone; camera; autoplay; encrypted-media; fullscreen" data-type="iframe" class="custom-field-frame" id="customFieldFrame_88" src="" style="max-width:250px;border:none;width:100%;height:200px" data-width="250" data-height="200"></iframe>
            <div class="widget-inputs-wrapper"><input type="hidden" id="input_88" class="form-hidden form-widget widget-required " name="q88_dibujarDentro" value="" /><input type="hidden" id="widget_settings_88" class="form-hidden form-widget-settings" value="%5B%5D" data-version="2" /></div>
            <script type="text/javascript">
              setTimeout(function()
              {
                var _cFieldFrame = document.getElementById("customFieldFrame_88");
                if (_cFieldFrame)
                {
                  _cFieldFrame.onload = function()
                  {
                    if (typeof widgetFrameLoaded !== 'undefined')
                    {
                      widgetFrameLoaded(88,
                      {
                        "formID": 231384136753659
                      })
                    }
                  };
                  _cFieldFrame.src = "//data-widgets.jotform.io/signature-pad/?padWidth=200&padHeight=180&qid=88&ref=" +
                    encodeURIComponent(window.location.protocol + "//" + window.location.host) + '' + '' + '' +
                    '&injectCSS=' + encodeURIComponent(window.location.search.indexOf("ndt=1") > -1);
                  _cFieldFrame.addClassName("custom-field-frame-rendered");
                }
              }, 0);
            </script>
          </div>
        </div>
      </li>
      <li class="form-line" data-type="control_button" id="id_13">
        <div id="cid_13" class="form-input-wide">
          <div data-align="center" class="form-buttons-wrapper form-buttons-center   jsTest-button-wrapperField"><button id="input_13" type="submit" class="form-submit-button form-submit-button-light_rounded submit-button jf-form-buttons jsTest-submitField" data-component="button" data-content="">Enviar Firmado</button></div>
        </div>
      </li>
      <li style="display:none">Should be Empty: <input type="text" name="website" value="" /></li>
    </ul>
  </div>
  <script>
    JotForm.showJotFormPowered = "0";
  </script>
  <script>
    JotForm.poweredByText = "Powered by Jotform";
  </script><input type="hidden" class="simple_spc" id="simple_spc" name="simple_spc" value="231384136753659" />
  <script type="text/javascript">
    var all_spc = document.querySelectorAll("form[id='231384136753659'] .si" + "mple" + "_spc");
    for (var i = 0; i < all_spc.length; i++)
    {
      all_spc[i].value = "231384136753659-231384136753659";
    }
  </script>
</form><script type="text/javascript">JotForm.ownerView=true;</script><script type="text/javascript">JotForm.forwardToEu=true;</script>


@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop

@section('js')

@stop
