Ext.namespace('CB');

/**
 * 2-step verification
 * Keep the bad guys out of your account by using both your password and your phone.
 */

Ext.define('CB.TSVWindow', {
    extend: 'Ext.Window'
    ,modal: true
    ,title: L.TSV
    ,autoWidth: true
    ,autoHeight: true
    ,border: false
    ,iconCls: 'fa fa-key'
    ,layout: 'card'

    ,initComponent: function() {
        Ext.apply(this, {
            activeItem: 0
            ,bodyBorder: false
            ,items: [{
                items: [{
                    xtype: 'displayfield'
                    ,style: 'padding: 10px; font-size: 20px;'
                    ,value: 'Select authentication method'
                },{
                    xtype: 'displayfield'
                    ,value: '<a class="click" name="ga">Google Authenticator</a>'
                    ,style: 'padding:10px'
                    ,name: 'ga'
                    ,listeners:{
                        scope: this
                        ,afterrender: function(c){
                            c.getEl().on('click', this.onTSVMechanismClick, this);
                        }
                    }
                // },{
                //     xtype: 'button'
                //     ,html: '<a>Sms message</a>'
                //     ,style: 'padding:10px'
                //     ,name: 'sms'
                //     ,scope: this
                //     ,handler: this.onTSVMechanismClick
                },{
                    xtype: 'displayfield'
                    ,value: '<a class="click" name="ybk">Yubikey</a>'
                    ,style: 'padding:10px'
                    ,name: 'ybk'
                    ,listeners:{
                        scope: this
                        ,afterrender: function(c){
                            c.getEl().on('click', this.onTSVMechanismClick, this);
                        }
                        ,loaded: this.onViewLoaded
                        ,verifyandsave: this.onVerifyAndSave
                    }
                }
                ]
            },{
                xtype: 'TSVgaForm'
                ,itemId: 'ga'
                ,listeners: {
                    scope: this
                    ,loaded: this.onViewLoaded
                    ,verifyandsave: this.onVerifyAndSave
                }
            },{
                xtype: 'TSVybkForm'
                ,itemId: 'ybk'
                ,listeners: {
                    scope: this
                    ,loaded: this.onViewLoaded
                    ,verifyandsave: this.onVerifyAndSave
                }
            }]
        });

        this.callParent(arguments);

        this.form = this.down('form');
    }

    ,onTSVMechanismClick: function(ev, el){
        this.TSVmethod = el.name;

        this.getLayout().setActiveItem(el.name);
        this.getLayout().activeItem.prepareInterface(this.data);
    }

    ,onVerifyAndSave: function(data){
        this.getEl().mask(L.Processing + ' ...', 'x-mask-loading');
        CB_User.enableTSV({
            method: this.TSVmethod
            ,data: data
        }, this.processEnableTSV, this);
    }

    ,onYubikeySaveClick: function(){
        this.getEl().mask(L.Processing + ' ...', 'x-mask-loading');
        CB_User.TSVSaveYubikey( { code: this.getLayout().activeItem.buttons[1].getValue() }, this.processEnableTSV, this);
    }

    ,processEnableTSV: function(r, e){
        this.getEl().unmask();

        if(r && (r.success === true)) {
            this.fireEvent('tsvchange', this, this.TSVmethod);
            this.destroy();
        } else {
            this.getLayout().activeItem.showError(r.msg);
            // this.syncSize();
        }
    }

    ,onViewLoaded: function() {
        this.center();
        this.center();

    }
}
);


Ext.define('CB.TSVgaForm', {
    extend: 'Ext.Panel'
    ,alias: 'widget.TSVgaForm'

    ,style: 'background-color: #fff'
    ,width:500

    ,initComponent: function(){
        Ext.apply(this, {
            bodyStyle: 'padding: 10px'
            ,items: [{
                xtype: 'displayfield'
                ,style: 'font-size: 20px; padding-bottom:15px'
                ,value: 'Set up Google Authenticator'
            },{
                autoHeight: true
                ,autoWidth:true
                ,border: false
                ,tpl: [
                    '<tpl for="data">'
                    ,'<p class="fwB">Install the Google Authenticator app for your phone</p>'
                    ,'<ol class="ol p10">'
                    ,'<li> On your phone, open a web browser. </li>'
                    ,'<li> Go to <span class="fwB">m.google.com/authenticator</span>. </li>'
                    ,'<li> Download and install the Google Authenticator application. </li>'
                    ,'</ol>'
                    ,'<p class="fwB"> Now open and configure Google Authenticator. </p>'
                    ,'<br /><p>Scan following Barcode to register the application automaticly:<p>'
                    ,'<div class="taC p10">'
                    ,'    <img src="{url}" width="100" height="100" />'
                    ,'</div>'
                    ,'<p> Or use the following secret key to register the aplication manually:</p>'
                    ,'<div class="taC p10 bgcY">'
                    ,'    <div class="fs14 fwB" dir="ltr">{sd}</div>'
                    ,'    <div class="fs10 cG">Spaces don\'t matter.</div>'
                    ,'</div><br />'
                    ,'<p> Once you manually entered and saved your key, enter the 6-digit verification code generated<br /> by the Authenticator app. </p>'
                    ,'</tpl>'
                ]
                ,data: {}
            }
            ]
            ,buttonAlign: 'left'
            ,buttons: [{
                    xtype: 'displayfield'
                    ,value: 'Code: '
                },{
                    xtype: 'textfield'
                    ,name: 'code'
                    ,width: '50'
                    ,enableKeyEvents: true
                    ,listeners: {
                        scope: this
                        ,keyup: function(field, e){
                            this.down('[name="btnVS"]').setDisabled(Ext.isEmpty(field.getValue()));
                        }
                    }
                },{
                    xtype: 'button'
                    ,text: L.VerifyAndSave
                    ,name: 'btnVS'
                    ,disabled: true
                    ,scope: this
                    ,handler: this.onVerifyAndSaveClick
                },{
                    xtype: 'displayfield'
                    ,style: 'padding: 0 0 0 20px'
                    ,name: 'errorMsg'
                    ,cls: 'cR'
                    ,value: ''
                    ,hidden: true
                }
            ]
        });

        this.callParent(arguments);
        // this.enableBubble(['verifyandsave']);
    }

    ,prepareInterface: function(data){
        this.getEl().mask(L.Processing + ' ...', 'x-mask-loading');
        CB_User.getTSVTemplateData('ga', this.processGetTSVTemplateData, this);
    }

    ,processGetTSVTemplateData: function(r, e){
        this.getEl().unmask();

        if(!r || (r.success !== true)) {
            return;
        }

        var p = this.items.getAt(1);

        p.data = r;
        p.update(r);

        this.down('[name="code"]').focus();
        this.fireEvent('loaded', this, e);
    }

    ,onVerifyAndSaveClick: function(){
        this.fireEvent('verifyandsave', {
            code: this.down('[name="code"]').getValue()
        });
    }

    ,showError: function(msg){
        if(Ext.isEmpty(msg)) {
            msg = 'The code is incorrect. Try again';
        }
        msg = '<img class="icon icon-exclamation fl" style="margin-right: 15px" src="/css/i/s.gif">'+ msg;

        var t = this.down('[name="errorMsg"]');
        t.setValue(msg);
        t.setVisible(true);
    }
});

Ext.define('CB.TSVybkForm', {
    extend: 'Ext.form.FormPanel'
    ,alias: 'widget.TSVybkForm'
    ,xtype: 'CBTSVybkForm'

    ,monitorValid: true
    ,autoWidth: true
    ,autoHeight: true
    ,labelWidth: 70
    ,buttonAlign: 'left'
    ,cls: 'bgcW'

    ,initComponent: function(){
        Ext.apply(this, {
            bodyStyle: 'padding: 10px'
            ,items: [{
                xtype: 'displayfield'
                ,hideLabel: true
                ,style: 'font-size: 20px; padding-bottom:15px'
                ,value: 'Set up Yubikey Authenticator'
            },{
                autoHeight: true
                ,autoWidth:true
                ,border: false
                ,style: 'font-size: 13px; padding-bottom:15px'
                ,tpl: ['<tpl for=".">'
                    ,'<ol>'
                    ,'<li>1. Insert your YubiKey into a USB port.</li>'
                    ,'<li>2. Enter your email in the email field. </li>'
                    ,'<li>3. Select/Click the Code field, and touch the YubiKey button. </li>'
                    ,'<li>4. Click Save.</li>'
                    ,'</ol>'
                    ,'<br />'
                    ,'<p>Note that it may take up until 5 minutes until all validation servers know about your newly generated client.</p>'
                    ,'</tpl>'
                ]
                ,data: {}
            },{
                xtype: 'textfield'
                ,vtype: 'email'
                ,name: 'email'
                ,fieldLabel: L.Email
                ,width: 250
                ,allowBlank: false
                ,value: App.loginData.email
            },{
                xtype: 'textfield'
                ,name: 'code'
                ,fieldLabel: L.Code
                ,width: 250
                ,allowBlank: false
            },{
                xtype: 'displayfield'
                ,style: 'padding: 0 0 0 20px; display: block'
                ,cls: 'cR'
                ,value: ''
                ,hideLabel: true
                ,hidden: true
            }
            ]
            ,buttonAlign: 'left'
            ,buttons: [{
                    xtype: 'button'
                    ,text: L.Save
                    ,formBind: true
                    ,scope: this
                    ,handler: this.onSaveClick
                }
            ]
        });

        this.callParent(arguments);
        // this.enableBubble(['verifyandsave']);
    }
    ,prepareInterface: function(data){
        this.getForm().setValues(data);
    }
    ,onSaveClick: function(){
        this.fireEvent('verifyandsave', this.getForm().getValues());
    }
    ,showError: function(msg){
        if(Ext.isEmpty(msg)) {
            msg = 'The code is incorrect. Try again';
        }
        msg = '<img class="icon icon-exclamation fl" style="margin-right: 15px" src="/css/i/s.gif">'+ msg;
        this.items.getAt(4).setValue(msg);
        this.items.getAt(4).setVisible(true);
    }
});
