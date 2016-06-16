Ext.namespace('CB');

Ext.define('CB.ChangePasswordWindow', {
    extend: 'Ext.Window'
    ,modal: true
    ,title: L.ChangePassword
    ,autoWidth: true
    ,autoHeight: true
    ,border: false
    ,glyph: 0xf084

    ,initComponent: function() {
        var items = [];

        this.data = this.config.data;

        if(this.data.id == App.loginData.id)
            items = [{
                xtype: 'textfield'
                ,fieldLabel: L.CurrentPassword
                ,inputType: 'password'
                ,name: 'currentpassword'
                ,allowBlank: (this.data.id != App.loginData.id)
            }];
        items.push({
                xtype: 'textfield'
                ,fieldLabel: L.Password
                ,inputType: 'password'
                ,name: 'password'
                ,allowBlank: false
                ,shouldMatch: true
            },{
                xtype: 'textfield'
                ,fieldLabel: L.ConfirmPassword
                ,inputType: 'password'
                ,name: 'confirmpassword'
                ,allowBlank: false
                ,shouldMatch: true
            },{
                xtype: 'displayfield'
                ,hideLabel: true
                ,cls: 'cR taC'
                ,anchor: '100%'
                ,id: 'msgTarget'
                ,value: '&nbsp;'
            });

        Ext.apply(this, {
            items: {
                xtype: 'form'
                ,autoWidth: true
                ,autoHeight: true
                ,border: false
                ,monitorValid: true
                ,extraParams: this.data
                ,api: {
                    submit: CB_UsersGroups.changePassword
                }
                ,items: {
                    xtype: 'fieldset'
                    ,labelWidth: 150
                    ,autoWidth: true
                    ,autoHeight: true
                    ,border: false
                    ,layout: 'anchor'
                    ,style: 'padding-top: 10px'
                    ,defaults: {
                        anchor: '100%'
                        ,listeners: {
                            scope: this
                            ,invalid: function(field, msg){
                                if(field.getEl().hasCls('x-form-invalid')) this.hasInvalidFields = true;
                            }
                        }
                    }
                    ,items: items
                }
                ,listeners: {
                    scope: this
                    ,clientvalidation: function(form, valid){
                        var label = this.down('[id="msgTarget"]');

                        if(!valid && this.hasInvalidFields){
                            label.setValue(L.EmptyRequiredFields);
                            return;
                        }

                        var a = this.query('[shouldMatch=true]');

                        if(a[0].getValue() != a[1].getValue()){
                            this.down('form').buttons[0].setDisabled(true);
                            label.setValue(L.PasswordMissmatch);
                            return;
                        }

                        label.setValue('&nbsp;');
                    }
                }
                ,buttons: [
                    {
                        text: Ext.MessageBox.buttonText.ok
                        ,glyph: 0xf00c
                        ,formBind: true
                        ,type: 'submit'
                        ,scope: this
                        ,plugins: 'defaultButton'
                        ,handler: function(){
                            var f = this.down('form');
                            f.getForm().submit({
                                clientValidation: true
                                ,params: this.data
                                ,scope: this
                                ,success: this.onSubmitSuccess
                                ,failure: this.onSubmitFailure
                            });
                        }
                    },{
                        text: L.Cancel
                        ,glyph: 0xf00d
                        ,handler: this.destroy
                        ,scope: this
                    }
                ]
            }

            ,listeners: {
                afterrender: function(){
                    // var f = this.down('form');
                    // App.focusFirstField(f);
                }
            }
        });

        this.callParent(arguments);
    }
    ,onSubmitSuccess: function(r, e){
        clog('success', arguments);
        this.fireEvent('passwordchanged');
        this.destroy();
    }
    ,onSubmitFailure: function(r, e){
        Ext.Msg.alert(L.Error, e.result.message);
    }
}
);
