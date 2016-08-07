Ext.namespace('CB');

Ext.define('CB.file.edit.Window', {
    extend: 'CB.object.edit.Window'

    ,alias: 'CBFileEditWindow'

    ,xtype: 'CBFileEditWindow'

    ,width: 600
    ,height: 550

    ,initView: function () {
        this.editView = Ext.create('CBFileEditView', {
            data: this.data
            ,listeners: {
                scope: this
                ,loaded: this.onViewLoaded
                ,objectsaved: this.onObjectSavedOrClickedEvent
                ,cancelclick: this.onObjectSavedOrClickedEvent
            }
        });
    }
});
