Ext.namespace('CB.object.plugin');

Ext.define('CB.object.plugin.UserSecurity', {
    extend: 'CB.object.plugin.Html'
    ,alias: 'CBObjectPluginUserSecurity'

    ,title: 'Security'
    ,autoHeight: true

    ,onDataActionClick: function(ev, el) {
        el = Ext.get(el);
        if(el) {
            var w,
                action = el.getAttribute('data-action');

            this.userId = el.getAttribute('data-user-id');

            switch(action) {
                case 'pass-change':
                    w = new CB.ChangePasswordWindow({data: {id: this.userId}});
                    w.on('passwordchanged', this.fireReloadEvent, this);
                    w.show();
                    break;

                case 'pass-reset':
                    CB_UsersGroups.sendResetPassMail(
                        this.userId
                        ,function(r, e) {
                            if(r) {
                                Ext.Msg.alert(
                                    L.Info
                                    ,r.success ? L.EmailSent: L.ErrorOccured
                                );
                            }
                        }
                        ,this
                    );
                    break;

                case 'change-username':
                    this.onChangeUsernameClick(el.getAttribute('data-username'));
                    break;

                case 'tsv-disable':
                    Ext.Msg.confirm(
                        L.Disable + ' ' + L.TSV
                        ,L.DisableTSVConfirmation
                        ,function(b){
                            if(b === 'yes') {
                                CB_UsersGroups.disableTSV(
                                    this.userId
                                    ,this.fireReloadEvent
                                    ,this
                                );
                            }
                        }
                        ,this
                    );
                    break;

                case 'tsv-enable':
                    w = new CB.TSVWindow({
                        data: {id: this.userId}
                        ,listeners:{
                            scope: this
                            ,tsvchange: this.fireReloadEvent
                        }
                    });

                    w.show();
                    break;

                case 'user-disable':
                    CB_UsersGroups.setUserEnabled(
                        {
                            id: this.userId
                            ,enabled: false
                        }
                        ,this.fireReloadEvent
                        ,this
                    );
                    break;

                case 'user-enable':
                    CB_UsersGroups.setUserEnabled(
                        {
                            id: this.userId
                            ,enabled: true
                        }
                        ,this.fireReloadEvent
                        ,this
                    );
                    break;
            }
        }
        this.callParent(arguments);
    }

    ,onChangeUsernameClick: function(oldName){
        Ext.Msg.prompt(
            L.ChangeUsername,
            L.ChangeUsernameMessage,
            function(btn, text){
                if (btn === 'ok'){
                    if(Ext.isEmpty(text)) {
                        return Ext.Msg.alert(L.Error, L.UsernameCannotBeEmpty);
                    }

                    var r = /^[a-z0-9\._]+$/i;

                    if(Ext.isEmpty(r.exec(text))) {
                        return Ext.Msg.alert(L.Error, L.UsernameInvalid);
                    }

                    CB_UsersGroups.renameUser(
                        {
                            id: this.userId
                            ,name: text
                        }
                        ,function(r, e){
                            if(!r) {
                                return;
                            }

                            if(r.success !== true) {
                                return Ext.Msg.alert(L.Error, Ext.valueFrom(e.msg, L.ErrorOccured) );
                            }
                            this.fireReloadEvent();
                        }
                        ,this
                    );
                }
            },
            this,
            false,
            oldName
        );
    }

    ,fireReloadEvent: function() {
        App.fireEvent('objectchanged', {id: this.userId});
    }
});
