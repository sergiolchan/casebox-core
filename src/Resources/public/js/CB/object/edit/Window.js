Ext.namespace('CB');

Ext.define('CB.object.edit.Window', {
    extend: 'Ext.Window'
    ,alias: 'CBObjectEditWindow'

    ,xtype: 'CBObjectEditWindow'

    ,closable: true
    ,minimizable: true
    ,maximizable: true
    ,layout: 'fit'
    ,border: false
    ,minWidth: 200
    ,minHeight: 200
    ,width: 600
    ,height: 450
    ,iconCls: 'icon-none'
    ,scrollable: false

    ,initComponent: function() {
        this.data = Ext.apply({}, this.config.data);
        delete this.data.html;

        this.initView();

        this.updateWindowTitle();

        Ext.apply(this, {
            stateful: true
            ,stateId: 'oew' + this.data.template_id
            ,items: [
                this.editView
            ]
            ,listeners: {
                scope: this
                ,'show': this.onShowWindow
                ,beforeclose: this.onBeforeClose
            }
        });

        this.callParent(arguments);

        this.editView.load(this.data);
    }

    ,initView: function () {
        this.editView = Ext.create('CBObjectEditView', {
            data: this.data
            ,listeners: {
                scope: this
                ,loaded: this.onViewLoaded
                ,objectsaved: this.onObjectSavedOrClickedEvent
                ,cancelclick: this.onObjectSavedOrClickedEvent
            }
        });
    }

    ,onShowWindow: function(c) {
        this.getEl().focus(10);
    }

    ,onViewLoaded: function() {
        this.updateWindowTitle();
    }
    /**
     * method for updating window title and icon according to template and data
     * @return void
     */
    ,updateWindowTitle: function() {
        var templatesStore = CB.DB.templates
            ,d = Ext.valueFrom(this.editView.data, {})
            ,templateId = d.template_id
            ,title = Ext.valueFrom(d.name, d.title);

        if(Ext.isEmpty(title)) {
            title = L.New + ' ' + templatesStore.getProperty(templateId, 'name');
        }
        this.setTitle(Ext.String.htmlEncode(title));

        this.setIconCls(getItemIcon(d));
    }

    ,onBeforeClose: function(){
        if(this.editView.confirmDiscardChanges()) {
            return false;
        }
    }

    ,onObjectSavedOrClickedEvent: function() {
        this.close();
    }
});
