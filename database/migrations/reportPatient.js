	$(document).ready(function() {    
var t = $("#patientsReportList2").DataTable({
    lengthMenu: [
        [ -1, 10, 25, 50, 100 ],
        [ "All", 10, 25, 50, 100 ]
    ],
    pageLength: -1, // ✅ DEFAULT = ALL

    columnDefs: [
        {
            targets: 1,
            width: "180px",
            className: "patient-movement-name",
        },
        {
            targets: 3,
            width: "220px",
            className: "patient-movement-address",
        },
        {
            targets: 5,
            width: "140px",
            className: "patient-movement-diagnosis",
        },
        {
            targets: [6, 7],
            width: "85px",
            className: "patient-movement-date",
        },
        {
            targets: 8,
            width: "130px",
            className: "patient-movement-remarks",
        },
        {
            searchable: false,
            orderable: false,
            targets: [0, -1],
        },
    ],
    order: [[1, "asc"]],
});

t.on('order.dt search.dt', function () {
    t.column(0, { search: 'applied', order: 'applied' })
     .nodes()
     .each(function (cell, i) {
        cell.innerHTML = i + 1;
     });
}).draw();

	var n = $('#ReportList2').DataTable({
		"columnDefs": [{
			"searchable": false,
			"orderable": false,
			"targets": [0,-1]
		}],
		"order": [[ 1, 'asc' ]]
	});
	n.on( 'order.dt search.dt', function () {
		n.column(0, {search:'applied', order:'applied'}).nodes().each( function (cell, i) {
			cell.innerHTML = i+1;
		});
	}).draw();
    
});
	function onCenterInput(){
	        var val = document.getElementById("inputC").value;
        	var x = document.getElementById("dlistC").className;
        	var y = document.getElementById("clinic");
            var opts = document.getElementById('dlistC').childNodes;
            for (var i = 0; i < opts.length; i++) {
              if (opts[i].value === val) {
        		document.getElementById("clinic").value = opts[i].className;
        		document.getElementById("clinicName").value = opts[i].value;
        		document.getElementById("filter").submit();

                break;
              }
            }
	    }
        function onInput() {
            var val = document.getElementById("input").value;
        	var x = document.getElementById("dlist").className;
        	var y = document.getElementById("clinic");
            var opts = document.getElementById('dlist').childNodes;
            for (var i = 0; i < opts.length; i++) {
              if (opts[i].value === val) {
        		document.getElementById("clinic").value = opts[i].className;
        		document.getElementById("clinicName").value = opts[i].value;
        		document.getElementById("filter").submit();

                break;
              }
            }
        }
		
		function dispClinicCenter(val){
			$("[name='modules[]']").attr('checked', false);
			if(val == '1'){

				$("#clinic").val('');
				$("#clinic").removeClass("required");
				$("#center").addClass("required");
				$("#center_disp").show();
				$("#clinic_disp").hide();
				$("#center_filter_disp").show();
			}else{


				$("#center").val('');
				$("#center").removeClass("required");
				$("#clinic").addClass("required");
				$("#center_disp").hide();
				$("#clinic_disp").show();
				$("#center_filter_disp").show();

			}
		}

		function disablefirstbutton(val) {
			alert(val);
			//document.getElementById("firstbutton").disabled = true;
		}

		$('#asset_module').click(function() {
			//$("#txtAge").toggle(this.checked);
			//alert('asset');
		});

		/*$(document).tooltip({

			items: "a", content: function () {

				var element = $(this);

				strImg = "<img src='" + element.attr('id') + "' />";

				var data = '';

				var strDesc = '';

				data = element.attr('title');

				if (element.attr('class') === 'showtitle') {

					return '<table width="600px" ><tr style="background-color:#2698DB"><td colspan="4" style="color:#ffffff;font-size:12px;font-weight:bold;" align="center">Billing Type</td></tr><tr><td><b>PANELS</b></td><td><b>GL REF NO</b></td><td><b>START DATE</b></td><td><b>EXPIRED DATE</b></td></tr>' + data + '</table>';

				}

			}

		});*/

		function patientInfo(patientID, show ,action) {
			$('.loading').show();
			iPage = $('.paginText').val();
			if (typeof(iPage) === 'undefined') {
				iPage = 1;
			}
			var strForm = '<form id="patientInfoForm" action="patient-info.html" method="post">';
			strForm += '<input type="hidden" name="p" value="' + iPage + '" />';
			strForm += '<input type="hidden" name="patient_id" value="' + patientID + '" />';
			strForm += '<input type="hidden" name="strShowSection" value="' + show + '" />';
			strForm += '<input type="hidden" name="pinfo" value="'+action+'" />';
			strForm += '</form>';
			$('#supplier-info-grid').append(strForm);
			$('#patientInfoForm').submit();

		}
		function edit(patientID) {
			$('.loading').show();
			iPage = $('.paginText').val();
			if (typeof(iPage) === 'undefined') {
				iPage = 1;
			}
			var strForm = '<form id="addpatient" action="patients-registration.html" method="post">';
			strForm += '<input type="hidden" name="p" value="' + iPage + '" />';
			strForm += '<input type="hidden" name="id" value="' + patientID + '" />';
			strForm += '<input type="hidden" name="frompage" value="patientEdit" />';
			strForm += '<input type="hidden" name="editpage" value="editP" />';
			strForm += '</form>';
			$('#supplier-info-grid').append(strForm);
			$('#addpatient').submit();

		}
		function getPrintQuotation(patientid,clinicid){
			var strForm = '<form id="quotepatient" action="patients-quotes.html" method="post" target="_blank">';
			strForm += '<input type="hidden" name="quotation_patient_id" value="' + patientid + '" />';
			strForm += '<input type="hidden" name="quotation_clinic_id" value="' + clinicid + '" />';
			strForm += '</form>';
			$('#supplier-info-grid').append(strForm);
			$('#quotepatient').submit();
		}

		function getCashPrintQuotation(patientid,clinicid){
			var strForm = '<form id="cashquotepatient" action="patients-cashquotes.html" method="post" target="_blank">';
			strForm += '<input type="hidden" name="quotation_patient_id" value="' + patientid + '" />';
			strForm += '<input type="hidden" name="quotation_clinic_id" value="' + clinicid + '" />';
			strForm += '</form>';
			$('#supplier-info-grid').append(strForm);
			$('#cashquotepatient').submit();
		}

		function quotationSetting(patientid,clinicid){
			$('#quotation_patient_id').val(patientid);
			$('#quotation_clinicid').val(clinicid);

			$.ajax({
				url: 'getRecords.html',
				data: 't=getQDetails&p=' + patientid,
				success: function (strResponse) {
					var qtDetails = $.parseJSON(strResponse);
					console.log(qtDetails);
					if(qtDetails['quotesInfo'] != false) {
						$('#quotation_date').val(qtDetails['quotesInfo']['quotation_date']);
						$('#treatment_sdialysis[value='+qtDetails['quotesInfo']['treatment_sdialysis']+']').prop('checked', true);
						$('#treatment_sepo[value='+qtDetails['quotesInfo']['treatment_sepo']+']').prop('checked', true);
						$('#treatment_dialysis option[value="'+qtDetails['quotesInfo']['treatment_dialysis']+'"]').prop('selected',true);
						$('#treatment_epo option[value='+qtDetails['quotesInfo']['treatment_epo']+']').attr('selected','selected');
						$('#treatment_dia_no').val(qtDetails['quotesInfo']['treatment_dia_no']);
						$('#treatment_epo_no').val(qtDetails['quotesInfo']['treatment_epo_no']);
						if(qtDetails['quotesInfo']['treatment_epo_dose'] !=''){
							$('#treatment_epo').change();
							setTimeout(function(){
								$('#treatment_epo_dose option[value='+qtDetails['quotesInfo']['treatment_epo_dose']+']').attr('selected','selected');
							},2000);

						}
						$('#treatment_date').val(qtDetails['quotesInfo']['treatment_date']);
						$('#price_sdialysis[value='+qtDetails['quotesInfo']['price_sdialysis']+']').prop('checked', true);
						$('#price_sepo[value='+qtDetails['quotesInfo']['price_sepo']+']').prop('checked', true);

						$('#price_dia_amount').val(qtDetails['quotesInfo']['price_dia_amount']);
						$('#price_epo_amount').val(qtDetails['quotesInfo']['price_epo_amount']);
						$('#price_dia_time').val(qtDetails['quotesInfo']['price_dia_time']);
						$('#price_epo_time').val(qtDetails['quotesInfo']['price_epo_time']);
						$('#price_dia_total').val(qtDetails['quotesInfo']['price_dia_total']);
						$('#price_epo_total').val(qtDetails['quotesInfo']['price_epo_total']);

						$('#md').val(qtDetails['quotesInfo']['md']);
						$('#ahb').val(qtDetails['quotesInfo']['ahb']);
						$('#ed').val(qtDetails['quotesInfo']['ed']);
						$('#nephro').val(qtDetails['quotesInfo']['nephro']);
						$('#vi').val(qtDetails['quotesInfo']['vi']);
						$('#formatAdd').val('update');
						$('#addquotation').val('2');

					} else {

						$('#treatment_dia_no').val('12-14');
					    $('#treatment_epo_no').val('12-14');

						$('#price_dia_time').val(qtDetails['panelInfo'][0]['dialysis_no']);
					    $('#price_epo_time').val(qtDetails['panelInfo'][0]['epo_no']);
						$('#price_dia_amount').val(qtDetails['panelInfo'][0]['dialysis_amount']);
						$('#price_dia_total').val(qtDetails['panelInfo'][0]['monthly_limit_amt']);
						$('#price_epo_amount').val(qtDetails['panelInfo'][0]['epo_amount']);
						$('#price_epo_total').val(qtDetails['panelInfo'][0]['epo_monthly_limit_amt']);

						$('#md').val('50');
						$('#ahb').val('60');
						$('#ed').val('50');
						$('#nephro').val('100');
						$('#vi').val('60');

						$('#formatAdd').val('add');
					    $('#addquotation').val('1');
					}
				}
			});

			$('#quoteSettingDiv').modal({
					containerCss: {
						height: '320px',
						width: '900px'
					}
			});

		}
		$('#treatment_epo').change(function(){
			var epo_id = $("#treatment_epo").find(':selected').attr('epoid');
			$.ajax({
				url: 'ca.ai',
				data: 't=getepodropdown&ds=' + epo_id,
				success: function ( objResponse ) {
					$('#treatment_epo_dose').html(objResponse);
				}
			});
		});

        
    function updatePatient(iPatientId) {
		$('#id').val(iPatientId);
		$('#iPage').val('1');
		if ($('.paginText').val()) {
			$('#iPage').val($('.paginText').val());
		}
		$('#updatePatientForm').submit();
	}

	function deletePatient(ipatientId) {
		$('#delete_id').val(ipatientId);
		$('#deliPage').val('1');
		if ($('.paginText').val()) {
			$('#deliPage').val($('.paginText').val());
		}
		$('#patientDelete').modal({
			containerCss: {
				height: '140px',
				width: '500px'
			}
		});				
	}
