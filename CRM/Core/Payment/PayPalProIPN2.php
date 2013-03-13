<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.3                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2013                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*/

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2013
 * $Id$
 *
 */
class CRM_Core_Payment_PayPalProIPN2 extends CRM_Core_Payment_BaseIPN {
  protected $inputVariables = array();
  static $_paymentProcessor = NULL;
  protected $billingID = null;
  protected $component = null;

  function __construct() {
    parent::__construct();
    $ids = array();
    if (!$this->getBillingID($ids)) {
      CRM_Core_Error::debug_log_message("billing location type not defined for your site");
      echo ts(("billing location type not defined for your site"));
      exit();
    }
    $this->billingID = $ids['billing'];
  }


  /**
   * To test the IPN ::
   *function test(){
    civicrm_initialize();
    $ipn = new CRM_Core_Payment_PayPalProIPN2;
    $input = array(
      'mc_gross' => '12.00',
      'tax' => '0.00',
      'payer_id' => 'xxxx',
      'payment_status' => 'Completed',
      'rp_invoice_id' => 'i=jhkjhkhk8&m=contribute&c=1&r=2&b=3&p=6',
      'recurring_payment_id' => 'I-jhkhkh',
      'first_name' => 'bob',
      'mc_fee' => '0.56',
      'amount' => '12.00',
      'txn_id' => 'jhkhk',
      'payment_type' => 'instant',
      'last_name' => 'Bob',
      'payment_fee' => '0.56',
      'txn_type' => 'recurring_payment',
      'effective_date' => '2013-03-11',
   );
  $ipn->main('contribute', $input);
  }
   */
/**
 * retrive POST var
 * @param string $name
 * @param string $type
 * @param string $location
 * @param boolean $abort
 * @return unknown
 */
  static function retrieve($name, $type, $location = 'POST', $abort = TRUE) {
    static $store = NULL;
    $value = CRM_Utils_Request::retrieve($name, $type, $store,
      FALSE, NULL, $location
    );
    if ($abort && $value === NULL) {
      CRM_Core_Error::debug_log_message("Could not find an entry for $name in $location");
      echo "Failure: Missing Parameter<p>";
      exit();
    }
    return $value;
  }
/**
 * Function for recurring contributions
 *
 * @param unknown_type $input
 * @param unknown_type $ids
 * @param unknown_type $objects
 * @param unknown_type $first
 * @return void|boolean
 */
  function recur(&$input, &$ids, &$objects, $first) {
    if (!isset($input['txnType'])) {
      CRM_Core_Error::debug_log_message("Could not find txn_type in input request");
      echo "Failure: Invalid parameters<p>";
      return FALSE;
    }

    if ($input['txnType'] == 'recurring_payment' &&
      $input['paymentStatus'] != 'Completed'
    ) {
      CRM_Core_Error::debug_log_message("Ignore all IPN payments that are not completed");
      echo "Failure: Invalid parameters<p>";
      return FALSE;
    }

    $recur = &$objects['contributionRecur'];

    // make sure the invoice ids match
    // make sure the invoice is valid and matches what we have in
    // the contribution record
    if ($recur->invoice_id != $input['invoice']) {
      CRM_Core_Error::debug_log_message("Invoice values dont match between database and IPN request recur is " . $recur->invoice_id . " input is " . $input['invoice']);
      echo "Failure: Invoice values dont match between database and IPN request recur is " . $recur->invoice_id . " input is " . $input['invoice'];
      return FALSE;
    }

    $now = CRM_Utils_Array::value('effective_date', $input, date('YmdHis'));

    // fix dates that already exist
    $dates = array('create', 'start', 'end', 'cancel', 'modified');
    foreach ($dates as $date) {
      $name = "{$date}_date";
      if ($recur->$name) {
        $recur->$name = CRM_Utils_Date::isoToMysql($recur->$name);
      }
    }

    $sendNotification = FALSE;
    $subscriptionPaymentStatus = NULL;
    //List of Transaction Type
    /*
         recurring_payment_profile_created          RP Profile Created
         recurring_payment           RP Sucessful Payment
         recurring_payment_failed                               RP Failed Payment
         recurring_payment_profile_cancel           RP Profile Cancelled
         recurring_payment_expired         RP Profile Expired
         recurring_payment_skipped        RP Profile Skipped
         recurring_payment_outstanding_payment      RP Sucessful Outstanding Payment
         recurring_payment_outstanding_payment_failed          RP Failed Outstanding Payment
         recurring_payment_suspended        RP Profile Suspended
         recurring_payment_suspended_due_to_max_failed_payment  RP Profile Suspended due to Max Failed Payment
        */



    //Changes for paypal pro recurring payment

    switch ($input['txnType']) {
      case 'recurring_payment_profile_created':
        $recur->create_date = $now;
        $recur->contribution_status_id = 2;
        $recur->processor_id = $input['recurring_payment_id'];
        $recur->trxn_id = $recur->processor_id;
        $subscriptionPaymentStatus = CRM_Core_Payment::RECURRING_PAYMENT_START;
        $sendNotification = TRUE;
        break;

      case 'recurring_payment':
        if ($first) {
          $recur->start_date = $now;
        }
        else {
          $recur->modified_date = $now;
        }

        //contribution installment is completed
        if ($input['profile_status'] == 'Expired') {
          $recur->contribution_status_id = 1;
          $recur->end_date = $now;
          $sendNotification = TRUE;
          $subscriptionPaymentStatus = CRM_Core_Payment::RECURRING_PAYMENT_END;
        }

        // make sure the contribution status is not done
        // since order of ipn's is unknown
        if ($recur->contribution_status_id != 1) {
          $recur->contribution_status_id = 5;
        }
        break;
    }

    $recur->save();

    if ($sendNotification) {
      $autoRenewMembership = FALSE;
      if ($recur->id &&
        isset($ids['membership']) && $ids['membership']
      ) {
        $autoRenewMembership = TRUE;
      }
      //send recurring Notification email for user
      CRM_Contribute_BAO_ContributionPage::recurringNotify($subscriptionPaymentStatus,
        $ids['contact'],
        $ids['contributionPage'],
        $recur,
        $autoRenewMembership
      );
    }

    if ($input['txnType'] != 'recurring_payment') {
      return;
    }

    if (!$first) {
      // create a contribution and then get it processed
      $contribution = new CRM_Contribute_BAO_Contribution();
      $contribution->contact_id = $ids['contact'];
      $contribution->contribution_type_id  = $objects['contributionType']->id;
      $contribution->contribution_page_id = $ids['contributionPage'];
      $contribution->contribution_recur_id = $ids['contributionRecur'];
      $contribution->receive_date = $now;
      $contribution->currency = $objects['contribution']->currency;
      $contribution->payment_instrument_id = $objects['contribution']->payment_instrument_id;
      $contribution->amount_level = $objects['contribution']->amount_level;

      $objects['contribution'] = &$contribution;
    }

    $this->single($input, $ids, $objects,
      TRUE, $first
    );
  }

  function single(&$input, &$ids, &$objects, $recur = FALSE, $first = FALSE) {
    $contribution = &$objects['contribution'];

    // make sure the invoice is valid and matches what we have in the contribution record
    if ((!$recur) || ($recur && $first)) {
      if ($contribution->invoice_id != $input['invoice']) {
        CRM_Core_Error::debug_log_message("Invoice values dont match between database and IPN request");
        echo "Failure: Invoice values dont match between database and IPN request<p>contribution is" . $contribution->invoice_id . " and input is " . $input['invoice'];
        return FALSE;
      }
    }
    else {
      $contribution->invoice_id = md5(uniqid(rand(), TRUE));
    }

    if (!$recur) {
      if ($contribution->total_amount != $input['amount']) {
        CRM_Core_Error::debug_log_message("Amount values dont match between database and IPN request");
        echo "Failure: Amount values dont match between database and IPN request<p>";
        return FALSE;
      }
    }
    else {
      $contribution->total_amount = $input['amount'];
    }

    $transaction = new CRM_Core_Transaction();

    // fix for CRM-2842
    //  if ( ! $this->createContact( $input, $ids, $objects ) ) {
    //       return false;
    //  }

    $participant = &$objects['participant'];
    $membership = &$objects['membership'];

    $status = $input['paymentStatus'];
    if ($status == 'Denied' || $status == 'Failed' || $status == 'Voided') {
      return $this->failed($objects, $transaction);
    }
    elseif ($status == 'Pending') {
      return $this->pending($objects, $transaction);
    }
    elseif ($status == 'Refunded' || $status == 'Reversed') {
      return $this->cancelled($objects, $transaction);
    }
    elseif ($status != 'Completed') {
      return $this->unhandled($objects, $transaction);
    }

    // check if contribution is already completed, if so we ignore this ipn
    if ($contribution->contribution_status_id == 1) {
      $transaction->commit();
      CRM_Core_Error::debug_log_message("returning since contribution has already been handled");
      echo "Success: Contribution has already been handled<p>";
      return TRUE;
    }

    $this->completeTransaction($input, $ids, $objects, $transaction, $recur);
  }

/**
 *
 * @param string $component
 * @param array $inputVariables
 * @return boolean|Ambigous <void, boolean>
 */
  function main($component = 'contribute', $inputVariables = array()) {
    CRM_Core_Error::debug_var('GET', $_GET, TRUE, TRUE);
    CRM_Core_Error::debug_var('POST', $_POST, TRUE, TRUE);
    try{
      if(empty($inputVariables)){
        //if called via POST the function will retrieve via POST
        // but intention is it could equally be called by script - or unit test
        $inputVariables = $this->transformIPNData();
      }
      $this->component = $component;
      $this->inputVariables = $inputVariables;
      $ids = $this->getIDSFromRPInvoiceString($inputVariables['rp_invoice_id']);
      $input = array('component' => $component);
      $this->getInput($input, $ids);
      $this->processIPN($ids, $input);
    }
    catch (Exception $e){
      CRM_Core_Error::debug_log_message($e->getMessage());
      echo $e->getMessage();
      exit();
    }

  }

/**
 * This function gets all required fields from the $_POST array
 *
 * Am deliberately not transforming the fieldnames @ this stage so that the function can
 * be called by a test function within a script
 * Transformation is done by 'getInput'
 *
 * other fields returned but unused:
 * payment_cycle
 * next_payment_date
 * residence_country
 * initial_payment_amount
 * currency_code
 * time_created
 * verify_sign] => AnoNxk35R9A5EoGnB.dzrEwLrwWsAgo0LDKN1Pxy7NyZ7a0MU9-PDUlc
 * period_type
 * payer_status
 * tax
 * payer_email
 * first_name
 * receiver_email
 * payer_id
 * product_type
 * shipping
 * amount_per_cycle
 * charset
 * notify_version
 * amount
 * outstanding_balance
 * product_name
 * ipn_track_id
 *
 * @return array of relevant variables
 */
  function transformIPNData(){
    $ipnFields = array(
      'membershipID' => 'Integer',
      'rp_invoice_id' => 'String',
      'txn_type' => 'String',
      'payment_status' => 'String',
      'mc_gross' => 'Money',
      'ReasonCode' => 'String',
      'first_name' => 'String',
      'last_name' => 'String',
      'address_street' => 'String',
      'address_city' => 'String',
      'address_state' => 'String',
      'address_zip' => 'String',
      'address_country_code' => 'String',
      'test_ipn' => 'Integer',
      'mc_fee' => 'Money',
      'settle_amount' => 'Money',
      'txn_id' => 'String',
      'relatedContactID' => 'Integer',
      'onBehalfDupeAlert' => 'Integer',
      'recurring_payment_id' => 'String',
      'profile_status' => 'String',
    );
    $ipnVariables = array();
    foreach ($ipnFields as $field => $type){
      $ipnVariables[$field] = self::retrieve($field, $type, 'POST', FALSE);
    }
    return $ipnVariables;
  }
/**
 * break up rpstring to extract ids
 *
 * ids are stored in string in the format
 * i=4493ad32a14aae449a30b0abc2e59108&m=contribute&c=69856&r=651&b=160064&p=7
 * @param string $rpInvoiceString
 */
  function getIDSFromRPInvoiceString($rpInvoiceString){
    $idSpec = array(
      'contact' => array('field' => 'c', 'required' => TRUE),
      'contribution' => array('field' => 'b', 'required' => TRUE),
      'invoice' => array('field' => 'i', 'required' => TRUE),
      'contributionRecur' => array('field' => 'r', 'required' => FALSE),
    );
    if($this->component == 'event'){
      $idSpec['event'] = array('field' => 'e', 'required' => TRUE);
      $idSpec['participant'] = array('field' => 'p', 'required' => TRUE);
    }
    else{
      $idSpec['contributionPage'] = array('field' => 'p', 'required' => FALSE);
    }

    //convert rpinvoicestring to a keyed array
    $values = $ids = array();
    $rpInvoiceArray = explode('&', $rpInvoiceString);
    foreach ($rpInvoiceArray as $rpInvoiceValue) {
      $rpValueArray = explode('=', $rpInvoiceValue);
      $values[$rpValueArray[0]] = $rpValueArray[1];
    }
    foreach ($idSpec as $id => $spec) {
      if(!empty($values[$spec['field']])){
        $ids[$id] = $values[$spec['field']];
      }
      else{
        if($spec['required']){
          throw new Exception(ts('missing required id for '). $id . " in var " . $spec['field']);
        }
      }
    }
    return $ids;
  }

/**
 * Once ids & input have been extracted from the IPN request this function processes them
 * - this function can be called without the $_POST
 * @param array $ids
 * @param array $input
 */
  function processIPN($ids, $input){
    if (!$ids['membership'] && $ids['contributionRecur']) {
      $sql = "
    SELECT m.id
      FROM civicrm_membership m
INNER JOIN civicrm_membership_payment mp ON m.id = mp.membership_id AND mp.contribution_id = %1
     WHERE m.contribution_recur_id = %2
     LIMIT 1";
      $sqlParams = array(1 => array($ids['contribution'], 'Integer'),
        2 => array($ids['contributionRecur'], 'Integer'),
      );
      if ($membershipId = CRM_Core_DAO::singleValueQuery($sql, $sqlParams)) {
        $ids['membership'] = $membershipId;
      }
    }

    $paymentProcessorID = CRM_Core_DAO::getFieldValue('CRM_Core_DAO_PaymentProcessorType',
      'PayPal', 'id', 'name'
    );

    if (!$this->validateData($input, $ids, $objects, TRUE, $paymentProcessorID)) {
      return FALSE;
    }
    self::$_paymentProcessor = &$objects['paymentProcessor'];
    if ($this->component == 'contribute' || $this->component == 'event') {
      if ($ids['contributionRecur']) {
        // check if first contribution is completed, else complete first contribution
        $first = TRUE;
        if ($objects['contribution']->contribution_status_id == 1) {
          $first = FALSE;
        }
        return $this->recur($input, $ids, $objects, $first);
      }
      else {
        return $this->single($input, $ids, $objects, FALSE, FALSE);
      }
    }
    else {
      return $this->single($input, $ids, $objects, FALSE, FALSE);
    }
  }
/**
 * Construct input array
 * @param array $input
 * @param array $ids
 */
  function getInput(&$input, &$ids) {
   $billingID = $this->billingID;
   $inputVars = array(
     'txnType' => 'txn_type',
     'paymentStatus' => 'payment_status',
     'amount' => 'mc_gross',
     'reasonCode' => 'ReasonCode',
     "first_name" => 'first_name',
     "last_name" => 'last_name',
     "street_address-{$billingID}" => 'address_street',
     "city-{$billingID}" => 'address_city',
     "state-{$billingID}" => 'address_state',
     "postal_code-{$billingID}" => 'address_zip',
     "country-{$billingID}" => 'address_country_code',
     'is_test' => 'test_ipn',
     'fee_amount' => 'mc_fee',
     'net_amount' => 'settle_amount',
     'trxn_id' => 'txn_id',
     'recurring_payment_id' => 'recurring_payment_id',
     'profile_status' => 'profile_status',
     'effective_date' => 'effective_date',
   );
    $input['invoice'] = $ids['invoice'];
    foreach ($inputVars as $name => $paypalName) {
      $input[$name] = $this->inputVariables[$paypalName] ? $this->inputVariables[$paypalName] : NULL;
    }
  }

}

