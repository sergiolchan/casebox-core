Ext.namespace('CB.object.edit');

Ext.define('CB.object.edit.View', {
    extend: 'Ext.Panel'

    ,alias: 'CBObjectEditView'
    ,xtype: 'CBObjectEditView'

    ,border: false
    ,layout: 'border'

    ,closeAction: 'hide'
    ,scrollable: true

    //object data
    ,data: {}

    ,initComponent: function(){

        this.objectsStore = new CB.DB.DirectObjectsStore({
            listeners:{
                scope: this
                ,add: this.onObjectsStoreChange
                ,load: this.onObjectsStoreLoad
            }
        });

        //prepare interface components
        this.initActions();

        Ext.apply(this, {
            cls: 'x-panel-white'
            ,bodyStyle: 'border: 0; padding: 0; border-top: 1px solid #99bce8'

            ,tbar: [] //this.getToolbarButtons()

            ,items: [] //this.getLayoutItems()

            ,listeners: {
                scope: this
                ,'change': this.onChange
                ,'afterrender': this.onAfterRender
                // ,'beforeclose': this.onBeforeClose

                ,'openpreview': this.onOpenPreviewEvent
                ,'openproperties': this.onOpenPreviewEvent

                ,'editobject': this.onEditObjectEvent
                ,'editmeta': this.onEditObjectEvent
                ,'objectsaved': this.onObjectSavedEvent

                ,'fileupload': this.onFileUploadEvent

                ,'getdraftid': this.onGetDraftId

                ,'beforedestroy': this.onBeforeDestroy
            }
        });

        this.callParent(arguments);

        Ext.Direct.on('exception', this.onAppException, this);

        App.Favorites.on('change', this.onFavoritesChange, this);
    }

    ,onBeforeDestroy: function() {
        Ext.Direct.un('exception', this.onAppException, this);
        App.Favorites.un('change', this.onFavoritesChange, this);
    }

    /**
     * init this component actions
     * @return void
     */
    ,initActions: function() {
        this.actions = {
            edit: new Ext.Action({
                text: L.Edit
                ,glyph: 0xf040
                ,hidden: true
                ,scope: this
                ,handler: this.onEditClick
            })

            ,save: new Ext.Action({
                text: L.Save
                ,glyph: 0xf0c7
                // ,disabled: true
                ,hidden: true
                ,scope: this
                ,handler: this.onSaveClick
            })

            ,cancel: new Ext.Action({
                text: L.Cancel
                ,glyph: 0xf00d
                // ,hidden: true
                ,scope: this
                ,handler: this.onCancelClick
            })

            ,'delete': new Ext.Action({
                text: L.Delete
                ,scope: this
                ,handler: this.onDeleteClick
            })

            ,refresh: new Ext.Action({
                glyph: 0xf021
                ,scope: this
                ,handler: this.onRefreshClick
            })

            ,rename: new Ext.Action({
                text: L.Rename
                ,scope: this
                ,handler: this.onRenameClick
            })

            ,star: new Ext.Action({
                glyph: 0xf005
                ,qtip: L.Star
                ,itemId: 'star'
                ,hidden: true
                ,scope: this
                ,handler: this.onStarClick
            })

            ,unstar: new Ext.Action({
                glyph: 0xf006
                ,qtip: L.Unstar
                ,itemId: 'unstar'
                ,hidden: true
                ,scope: this
                ,handler: this.onUnstarClick
            })

            ,showInfoPanel: new Ext.Action({
                glyph: 0xf129
                ,enableToggle: true
                ,pressed: true
                ,scope: this
                ,handler: this.onShowInfoPanelClick
            })

            ,permalink: new Ext.Action({
                text: L.Permalink
                ,itemId: 'permalink'
                ,scope: this
                ,handler: this.onPermalinkClick
            })

            ,popout: new Ext.Action({
                glyph: 0xf08e
                ,itemId: 'popout'
                ,scope: this
                ,hidden: (App.popOutEdit === true)
                ,handler: this.onPopOutClick
            })

            ,notifyOn: new Ext.Action({
                text: L.NotifyOn
                ,hidden: true
                ,glyph: 0xf0f3
                ,itemId: 'notifyOn'
                ,scope: this
                ,handler: this.onSubscriptionButtonClick
            })

            ,notifyOff: new Ext.Action({
                text: L.NotifyOff
                ,hidden: true
                ,glyph: 0xf1f6
                ,itemId: 'notifyOff'
                ,scope: this
                ,handler: this.onSubscriptionButtonClick
            })
        };
    }

    /**
     * method that should return top toolbar buttons
     * @return array
     */
    ,getToolbarButtons: function() {
        if(this.templateType == 'time_tracking') {
            return [
                this.actions.edit
                ,this.actions.save
                ,this.actions.cancel
                ,'->'
                ,new Ext.Button({
                    qtip: L.More
                    ,itemId: 'more'
                    ,arrowVisible: false
                    ,glyph: 0xf142
                    ,menu: [
                        this.actions['delete']
                    ]
                })
            ];
        }

        return [
            this.actions.edit
            ,this.actions.save
            ,this.actions.cancel
            ,'->'
            ,this.actions.star
            ,this.actions.unstar
            ,this.actions.refresh
            ,this.actions.popout
            ,new Ext.Button({
                qtip: L.More
                ,itemId: 'more'
                ,arrowVisible: false
                ,glyph: 0xf142
                ,menu: [
                    this.actions['delete']
                    ,this.actions.rename
                    ,this.actions.permalink
                    ,'-'
                    ,this.actions.notifyOn
                    ,this.actions.notifyOff
                ]
            })
            ,this.actions.showInfoPanel
        ];
    }

    /**
     * initialize containers used
     * @return void
     */
    ,initContainerItems: function() {
        this.titleContainer = Ext.create({
            xtype: 'panel'
            ,border: false
            ,autoHeight: true
            ,items: []
        });

        this.complexFieldContainer = Ext.create({
            xtype: 'form'
            ,border: false
            ,autoHeight: true
            ,scrollable: false
            ,labelAlign: 'top'
            ,cls: 'complex-fieldcontainer'
            ,bodyStyle: 'margin: 0; padding: 0'
            ,api: {
                submit: CB_Objects.save
            }
            ,items: []
        });

        this.gridContainer = Ext.create('CB.object.plugin.ObjectProperties', {
            border: false
            ,autoHeight: true
            ,items: []
        });

        this.gridContainer.params = this.data;

        this.gridContainer.onItemChange = Ext.Function.createSequence(
            this.gridContainer.onItemChange
            ,Ext.Function.bind(this.loadPreviewData, this)
            ,this.gridContainer
        );

        this.pluginsContainer = Ext.create({
            xtype: 'CBObjectProperties'
            ,api: CB_Objects.getPluginsData
            ,border: false
            ,autoHeight: true
            ,scrollable: false
            ,listeners: {
                scope: this
                ,loaded: this.onPluginsContainerLoaded
            }
        });

        this.on('timespentclick', this.onTimeSpentClick, this);
        this.on('addtimespentclick', this.onAddTimeSpentClick, this);
    }

    /**
     * function that should return items structure based on template config
     * @return array
     */
    ,getLayoutItems: function() {
        var rez = [
            {
                region: 'center'
                ,scrollable: true
                ,border: false
                ,layout: {
                    type: 'vbox'
                    ,align: 'stretch'
                }
                ,items: [
                    this.titleContainer
                    ,this.gridContainer
                    ,this.complexFieldContainer
                    ,{
                        itemId: 'infoPanel'
                        ,border: false
                        ,bodyStyle: 'padding-top: 15px'
                        ,autoHeight: true
                        ,items: [
                            this.pluginsContainer
                        ]
                    }
                ]
            }
        ];

        //hide infopanel switcher by default, for vertical layout
        this.actions.showInfoPanel.setHidden(true);

        if((this.templateCfg.layout !== 'vertical') || (this.templateType === 'file')) {
            this.complexFieldContainer.flex = 1;
            this.complexFieldContainer.layout = 'fit';

            this.actions.showInfoPanel.setHidden(false);

            rez = [
                {
                    region: 'center'
                    ,border: false
                    ,bodyStyle: 'border-bottom:0; border-left: 0'
                    ,scrollable: true
                    ,layout: {
                        type: 'vbox'
                        ,align: 'stretch'
                    }
                    ,items: [
                        this.titleContainer
                        ,this.gridContainer
                        ,this.complexFieldContainer
                    ]
                }, {
                    region: 'east'
                    ,itemId: 'infoPanel'
                    ,header: false
                    ,border: false
                    ,scrollable: true
                    ,layout: {
                        type: 'vbox'
                        ,align: 'stretch'
                    }

                    ,split: {
                        size: 2
                    }

                    ,width: 300
                    ,items: [
                        this.pluginsContainer
                    ]
                }
            ];
        }

        return rez;
    }

    ,onAfterRender: function(c) {
        // map multiple keys to multiple actions by strings and array of codes
        var map = new Ext.KeyMap(
            c.getEl()
            ,[{
                key: 's'
                ,ctrl:true
                ,shift:false
                ,stopEvent: true
                ,scope: this
                ,fn: this.onSaveObjectEvent
            }]
        );

        //attach key listeners to grid view
        if(this.grid) {
            new Ext.util.KeyMap({
                target: this.grid.getView().getEl()
                ,binding: [{
                        key: 's'
                        ,ctrl: true
                        ,shift: false
                        ,stopEvent: true
                        ,scope: this
                        ,fn: this.onSaveObjectEvent
                    },{
                        key: Ext.event.Event.ESC
                        ,ctrl: false
                        ,shift: false
                        ,scope: this
                        ,stopEvent: true
                        ,fn: this.close
                    }
                ]
            });
        }
    }

    /**
     * clear containers method
     * @return void
     */
    ,clearContainers: function() {
        this.complexFieldContainer.removeAll(true);
        this.complexFieldContainer.update('');

        this.gridContainer.removeAll(false);
        this.gridContainer.update('');
    }

    ,load: function(data) {
        this.data = Ext.valueFrom(data, this.data);
        this.initialConfig.data = this.data;

        //init viewMode (preview / edit)
        this.viewMode = Ext.valueFrom(this.data.view, 'preview');

        //get template config
        this.templateCfg = CB.DB.templates.getProperty(this.data.template_id, 'cfg');

        //get template type
        this.templateType = CB.DB.templates.getType(this.data.template_id);

        var tbar = this.dockedItems.get(0);
        tbar.removeAll();
        tbar.add(this.getToolbarButtons());

        this.removeAll(true);

        this.initContainerItems();

        //create and add title view
        this.titleView = new CB.object.TitleView();
        this.titleContainer.add(this.titleView);
        this.titleContainer.setVisible(this.templateType !== 'time_tracking');

        this.add(this.getLayoutItems());

        this.doLoad();
    }

    /**
     * redirection method to corresponding load method depending on current viewModeSet
     * @return void
     */
    ,doLoad: function(data) {
        this.clearContainers();
        this['load' + Ext.util.Format.capitalize(this.viewMode) + 'Data']();
    }

    /**
     * method for loading preview data for current item
     * @return void
     */
    ,loadPreviewData: function() {
        if(this.templateType == 'time_tracking') {
            return;
        }

        CB_Objects.getPluginsData(
            {
                id: this.data.id
                // ,from: 'window'
            }
            ,this.processLoadPreviewData
            ,this
        );
        // this.updateButtons();
    }

    /**
     * method for loading data into edit mode
     * @return void
     */
    ,loadEditData: function() {

        var data = this.data;

        // for a new object we just load template locally
        if(isNaN(data.id)) {
            if(Ext.isEmpty(data.name)) {
                data.name = L.New + ' ' + CB.DB.templates.getName(data.template_id);
            }

            this.processLoadEditData({
                    success: true
                    ,data: data
                }
            );
        } else {
            CB_Objects.load(
                {id: this.data.id}
                ,this.processLoadEditData
                ,this
            );
        }

        this.pluginsContainer.doLoad({
            id: this.data.id
            ,template_id: this.data.template_id
            ,from: 'window'
        });
    }

    /**
     * method for processing server data on loading preview
     * @return void
     */
    ,processLoadPreviewData: function(r, e) {
        if(!r || (r.success !== true)) {
            return;
        }

        var objProperties  = Ext.valueFrom(r.data.objectProperties, {}).data
            ,preview = Ext.valueFrom(objProperties, {}).preview;

        //delete preview property from object data if set
        if(preview) {
            delete objProperties.preview;
        }

        this.data = Ext.apply(Ext.valueFrom(this.data, {}), objProperties);
        this.data.from = 'window';

        this.titleView.update(this.data);

        delete r.data.objectProperties;
        delete r.data.thumb;

        if(preview) {
            if(this.gridContainer.rendered) {
                this.gridContainer.update(preview[0]);
            } else {
                this.gridContainer.html = preview[0];
            }

            var cfp = Ext.valueFrom(preview[1], '');
            if(this.complexFieldContainer.rendered) {
                this.complexFieldContainer.update(cfp);
            } else {
                this.complexFieldContainer.html = cfp;
            }

        } else {
            this.gridContainer.hide();
            if(this.complexFieldContainer.rendered) {
                this.complexFieldContainer.update('');
            } else {
                this.complexFieldContainer.html = '';
            }
        }

        this.pluginsContainer.loadedParams = this.data;

        this.pluginsContainer.onLoadData(r, e);

        this.postLoadProcess();
    }

    /**
     * method for processing server data on editing item
     * @return void
     */
    ,processLoadEditData: function(r, e) {
        if(!r || (r.success !== true)) {
            return;
        }

        this.data = r.data;
        if(Ext.isEmpty(this.data.data)) {
            this.data.data = {};
        }

        this.titleView.update(this.data);

        r.data.from = 'window';
        this.pluginsContainer.loadedParams = r.data;

        this.objectsStore.proxy.extraParams = {
            id: r.data.id
            ,template_id: r.data.template_id
            ,data: r.data.data
        };

        //focus default grid cell if no comment given that should be scrolled and focused
        if(Ext.isEmpty(this.initialConfig.data.comment)) {
            this.startEditAfterObjectsStoreLoadIfNewObject = true;
        }

        this.objectsStore.reload();

        /* detect template type of the opened object and create needed grid */
        var gridType = (this.templateType === 'search')
            ? 'CBVerticalSearchEditGrid'
            : 'CBVerticalEditGrid';

        // if(this.lastgGridType != gridType) {
            this.gridContainer.removeAll(true);
            this.grid = Ext.create(
                gridType
                ,{
                    title: L.Details
                    ,autoHeight: true
                    ,hidden: true
                    ,refOwner: this
                    ,includeTopFields: true
                    ,stateId: 'oevg' //object edit vertical grid
                    ,autoExpandColumn: 'value'
                    ,scrollable: false
                    ,viewConfig: {
                        forceFit: true
                        ,autoFill: true
                    }
                    ,listeners: {
                        scope: this
                        ,savescroll: this.saveScroll
                        ,restorescroll: this.restoreScroll
                    }
                }
            );
        //     this.lastgGridType = gridType;

        // }
        this.gridContainer.add(this.grid);

        this.gridContainer.show();

        //add loading class that will hide the grid while objectsStore loads
        if(!this.objectsStore.isLoaded()) {
            this.grid.addCls('loading');
        }

        this.grid.hideTemplateFields = r.hideTemplateFields;
        this.grid.reload();

        if(this.grid.store.getCount() > 0) {
            this.grid.show();

            if(this.grid.rendered) {
                this.grid.getView().refresh(true);
            }
        }

        this.updateComplexFieldContainer();

        this._isDirty = false;

        this.postLoadProcess();
    }

    /**
     * method specific for complex field container update
     * based on loaded data
     * @return void
     */
    ,updateComplexFieldContainer: function() {
        if(this.grid.templateStore) {
            var fields = [];
            this.grid.templateStore.each(
                function(r) {
                    if(r.get('cfg').showIn === 'tabsheet') {
                        var cfg = {
                            border: false
                            ,isTemplateField: true
                            ,name: r.get('name')
                            ,value: this.data.data[r.get('name')]
                            ,height: Ext.valueFrom(r.get('cfg').height, 200)
                            ,anchor: '100%'
                            ,grow: true
                            ,title: r.get('title')
                            ,fieldLabel: r.get('title')
                            ,labelAlign: 'top'
                            ,labelCls: 'fwB ttU'
                            ,labelSeparator: ''
                            ,listeners: {
                                scope: this
                                ,change: function(field, newValue, oldValue) {
                                    this.fireEvent('change', field.name, newValue, oldValue);
                                }
                                ,sync: function(){
                                    this.fireEvent('change');
                                }
                            }
                            ,xtype: (r.get('type') === 'html')
                                ? 'CBHtmlEditor'
                                : 'textarea'
                        };
                        this.complexFieldContainer.add(cfg);
                    }
                }
                ,this
            );
        }

        this.complexFieldContainer.setVisible(this.complexFieldContainer.items.getCount() > 0);
    }

    /**
     * method called after preview or edit data has been loaded
     * @return void
     */
    ,postLoadProcess: function() {
        if(!this.hasLayout && this.updateLayout) {
            this.updateLayout();
        }

        this.updateButtons();

        if(this.gridContainer.rendered) {
            this.gridContainer.attachEvents();
        }

        this.fireEvent('loaded', this);
    }

    ,updateButtons: function() {
        if(this.viewMode === 'preview') {
            this.actions.edit.show();
            this.actions.save.hide();
            // this.actions.cancel.hide();

            this.actions.rename.show();
        } else {
            this.actions.edit.hide();
            this.actions.save.show();
            // this.actions.save.setDisabled(!this._isDirty);
            // this.actions.cancel.show();

            this.actions.rename.hide();
        }

        this.actions['delete'].setDisabled(!Ext.isNumeric(this.data.id));
        this.actions.popout.setHidden(App.popOutEdit === true);

        this.onFavoritesChange();
    }

    /**
     * listner method for change field values
     * @param  string fieldName
     * @param  variant newValue
     * @param  variant oldValue
     * @return void
     */
    ,onChange: function(fieldName, newValue, oldValue){
        this._isDirty = true;

        // this.actions.save.setDisabled(!this.isValid());

        if(!Ext.isEmpty(fieldName) && Ext.isString(fieldName)) {
            this.fireEvent('fieldchange', fieldName, newValue, oldValue);
        }

        //fire event after change event process
        this.fireEvent('changed', this);
    }

    ,onPluginsContainerLoaded: function(cmp, commonParams) {
        var icd = this.initialConfig.data;

        if(!Ext.isEmpty(icd.comment)) {
            cmp.setCommentValue(icd.comment);

            //scroll it into view
            var cc = cmp.getCommentComponent();
            if(cc) {
                var i = this.items.getAt(0);
                if(!i.scrollable) {
                    i = this.items.getAt(1);
                }
                cc.getEl().scrollIntoView(i.body, false, false, true);

                i.body.scrollBy(0, 40, false);

                cc.focus(false, 100);
            }

        }

        var subscription = Ext.valueFrom(commonParams.subscription, 'ignore');
        this.actions.notifyOn.setHidden(subscription === 'watch');
        this.actions.notifyOff.setHidden(subscription === 'ignore');
    }

    ,onSubscriptionButtonClick: function(b, e) {
        var type = (b.itemId === 'notifyOn')
            ? 'watch'
            : 'ignore';

        CB_Objects.setSubscription(
            {
                objectId: this.data.id
                ,type: type
            }
            ,function(r, e) {
                if(!r || (r.success !== true)) {
                    return;
                }

                this.actions.notifyOn.setHidden(type === 'watch');
                this.actions.notifyOff.setHidden(type === 'ignore');
            }
            ,this
        );
    }

    ,onNotificationsCustomizeClick: function(b, e) {
        var w = new CB.notifications.SettingsWindow();
        w.show();
    }

    /**
     * handler for edit toolbar button
     * @param  button b
     * @param  event e
     * @return void
     */
    ,onEditClick: function(b, e) {
        this.viewMode = 'edit';
        this.doLoad();
    }

    ,onSaveClick: function(b, e) {
        if(!this.isValid()) {
            var i = this.items.getAt(0)
                ,g = this.grid
                ,v = g.getView();
            if(!i.scrollable) {
                i = this.items.getAt(1);
            }
            Ext.get(v.getRow(g.invalidRecord)).scrollIntoView(i.body, null, false);

            return this.grid.focusInvalidRecord();

        }

        if(!this._isDirty) {
            return this.fireEvent('objectsaved', this.data, this);
        }

        this.readValues();

        this.getEl().mask(L.Saving + ' ...', 'x-mask-loading');

        this.saving = true;

        this.complexFieldContainer.getForm().submit({
            clientValidation: true
            ,loadMask: false
            ,params: {
                data: Ext.encode(this.data)
            }
            ,scope: this
            ,success: this.processSave
            ,failure: this.processSave
        });
    }

    ,onCancelClick: function(b, e) {
        if (!this.confirmDiscardChanges()) {
           this.fireEvent('cancelclick', this);
           this.onClose();
        }
    }

    ,onClose: function() {
        if (App.popOutEdit) {//when closing a popout window
            App.confirmLeave = false;
            window.close();
        }
    }

    /**
     * method for pocessing save responce
     * @param  component form
     * @param  object action
     * @return void
     */
    ,processSave: function(form, action) {
        this.getEl().unmask();
        delete this.saving;

        var r = action.result;

        if(!r || (r.success !== true)) {
            App.showException(r);
        } else {
            this._isDirty = false;
            App.fireEvent('objectchanged', r.data, this);
            this.fireEvent('objectsaved', r.data, this);
        }
    }

    ,onAppException: function() {
        if(this.saving) {
            delete this.saving;
            this.getEl().unmask();
        }
    }

    ,onObjectsStoreLoad: function(store, records, options) {
        this.onObjectsStoreChange(store, records, options);


        if(!this.grid.editing) {
            this.grid.getView().refresh();

            if(this.startEditAfterObjectsStoreLoadIfNewObject === true) {
                this.focusDefaultCell();
            }
        }

        this.grid.removeCls('loading');
    }

    ,onObjectsStoreChange: function(store, records, options){
        Ext.each(
            records
            ,function(r){
                r.set('iconCls', getItemIcon(r.data));
            }
            ,this
        );
    }

    /**
     * focus value column in first row, and start editing if it's a new object
     * @return void
     */
    ,focusDefaultCell: function() {
        if(this.grid &&
            !this.grid.editing &&
            this.grid.getEl() &&
            (this.grid.store.getCount() > 0)
        ) {
            var valueCol = this.grid.headerCt.child('[dataIndex="value"]');
            var colIdx = valueCol.getIndex();

            this.grid.getSelectionModel().select({row: 0, column: colIdx});
            this.grid.getNavigationModel().setPosition(0, colIdx);

            if(this.startEditAfterObjectsStoreLoadIfNewObject && isNaN(this.data.id)) {
                this.grid.editingPlugin.startEditByPosition({row: 0, column: colIdx});
            }

            delete this.startEditAfterObjectsStoreLoadIfNewObject;
        }
    }

    ,readValues: function() {
        this.grid.readValues();

        this.data.data = Ext.apply(
            this.data.data
            ,this.complexFieldContainer.getForm().getFieldValues()
        );

        return this.data;
    }

    /**
     * set value for a field
     *
     * TODO: review for duplicated fields, and for fields outside of the grid
     *
     * @param varchar fieldName
     * @param variant value
     */
    ,setFieldValue: function (fieldName, value) {
        if(this.grid) {
            this.grid.setFieldValue(fieldName, value);
        }
    }

    /**
     * reload  window
     * @param  button b
     * @param  event e
     * @return void
     */
    ,onRefreshClick: function(b, e) {
        this.doLoad();
    }

    /**
     * handler for show right panel toolbar button
     * @param  button b
     * @param  event e
     * @return void
     */
    ,onShowInfoPanelClick: function(b, e) {
        var ip = this.queryById('infoPanel');

        if(ip) {
            ip.setVisible(b.pressed);
        }
    }

    /**
     * handler for open preview from components below
     *
     * It was opening preview in current component,
     * when editing on the right side was available.
     * Now it opens popup window in preview mode.
     *
     * @param  object data
     * @param  event e
     * @return void
     */
    ,onOpenPreviewEvent: function(data, e) {
        if(Ext.isEmpty(data)) {
            data = Ext.clone(this.data);
        }

        if(this.data && (data.id == this.data.id)) {
            Ext.applyIf(data, this.data);
        }

        App.windowManager.openObjectWindow(Ext.clone(data), e);
    }

    /**
     * handler for open edit object event from components below
     *
     * It was opening edit in current component,
     * when editing on the right side was available.
     * Now it opens popup window in edit mode.
     *
     * @param  object data
     * @param  event e
     * @return void
     */
    ,onEditObjectEvent: function(data, e) {
        if(e) {
            e.stopEvent();
        }

        if(Ext.isEmpty(data)) {
            data = this.data;
        }

        var p = Ext.clone(data);

        p.view = 'edit';

        if(p.id == this.data.id) {
            this.viewMode = 'edit';
            this.doLoad();
        } else {
            App.windowManager.openObjectWindow(p);
        }
    }

    ,onDeleteClick: function(b, e) {
        this.getEl().mask(L.Processing + ' ...', 'x-mask-loading');

        CB.browser.Actions.deleteSelection(
            [this.data]
            ,this.processDelete
            ,this
        );

    }

    ,processDelete: function(r, e) {
        this.getEl().unmask();

        if(r && (r.success === true)) {
            this.fireEvent('cancelclick', this);
        }
    }

    /**
     * save scroll position method for vertical grid editor
     * @return variant cusrrent scroll position
     */
    ,saveScroll: function() {
        var gc = this.gridContainer.ownerCt;
        this.lastScroll = gc.body.getScroll();

        return this.lastScroll;
    }

    /**
     * restore scroll position method for vertical grid editor
     * @return void
     */
    ,restoreScroll: function() {
        var gc = this.gridContainer.ownerCt;
        gc.body.setScrollLeft(this.lastScroll.left);
        gc.body.setScrollTop(this.lastScroll.top);
    }

    ,onGetDraftId: function(callback, scope) {
        delete this.getDraftIdCallback;

        if(Ext.isEmpty(callback)) {
            callback = Ext.emptyFn;
        }

        this.getDraftIdCallback = scope
            ? Ext.Function.bind(callback, scope)
            : callback;

        if(!isNaN(this.data.id)) {
            this.getDraftIdCallback(this.data.id);

        } else {
            this.readValues();

            var data = Ext.apply({}, this.data);
            data.draft = true;

            CB_Objects.create(
                data
                ,this.processSaveDraft
                ,this
            );
        }
    }

    ,processSaveDraft: function(r, e) {
        if(!r || (r.success !== true)) {
            return;
        }

        var id = r.data.id;
        this.data.id = id;
        this.data.pid = r.data.pid;
        // this.data.draft = true;

        //update loadedData.id of the plugins container so it will reload automaticly
        //on fileuploaded event
        this.pluginsContainer.loadedParams = {
            id: id
            ,template_id: this.data.template_id
            ,from: 'window'
        };

        this.getDraftIdCallback(id, e);
    }

    ,onFileUploadEvent: function(p, e) {
        this.uploadFieldData = {
            pid: this.data.id
        };

        if(isNaN(this.uploadFieldData.pid)) {
            this.onGetDraftId(
                function(id, e) {
                    this.uploadFieldData.pid = id;
                }
                ,this
            );

        }

        App.mainViewPort.onFileUpload(this.uploadFieldData, e);
    }

    /**
     * validation check
     * @return Boolean
     */
    ,isValid: function(){
        var rez = true;
        if(this.grid && this.grid.isValid) {
            rez = this.grid.isValid();
        }

        return rez;
    }

    ,onRenameClick: function(b, e) {
        var d = this.data
            ,data = {
                path: d.id
                ,name: Ext.util.Format.htmlDecode(d.name)
                ,scope: this
                ,callback: function(r, e) {
                    this.data.name = r.data.newName;

                    this.titleView.update(this.data);
                }
            };

        App.promptRename(data);
    }

    ,onPermalinkClick: function(b, e) {
        window.prompt(
            'Copy to clipboard: Ctrl+C, Enter'
            , window.location.origin + '/' + App.config.coreName + '/view/' + this.data.id + '/'
        );
    }

    ,onPopOutClick: function(b, e) {
        this.popOutData = Ext.apply({popOut: true}, this.data);
        this.popOutOnSave = true;
        this.onSaveClick();
    }

    ,onObjectSavedEvent: function() {
        if(this.popOutOnSave) {//when poping out
            App.windowManager.openObjectWindow(this.popOutData);
        }

        this.onClose();
    }

    ,onStarClick: function(b, e) {
        var ld = this.data
            ,d = {
                id: ld.id
                ,name: ld.name
                ,iconCls: ld.iconCls
                ,glyph: ld.glyph
                ,path: '/' + ld.pids + '/' + ld.id
                ,pathText: ld.path
            };

        App.Favorites.setStarred(d);
    }

    ,onUnstarClick: function(b, e) {
        App.Favorites.setUnstarred(this.data.id);
    }

    ,onFavoritesChange: function() {
        var isStarred = App.Favorites.isStarred(this.data.id);

        this.actions.star.setHidden(isStarred);
        this.actions.unstar.setHidden(!isStarred);
    }

    ,onTimeSpentClick: function(cmp) {
        this.pluginsContainer.onTimeSpentClick(cmp);
    }

    ,onAddTimeSpentClick: function(cmp, e) {
        this.pluginsContainer.onAddTimeSpentClick(cmp, e);
    }

    ,getViewInfo: function() {
        return {
            path: '-1'
            ,pathtext: Ext.valueFrom(this.data.name, L.Edit)
        };
    }

    ,getSelection: function() {
        return [this.data];
    }

    ,isDirty: function() {
        return this._isDirty;
    }

    ,onSaveObjectEvent: function(objComp, ev) {
        ev.stopEvent();
        if(this.actions.save.isDisabled()) {
            return false;
        }
        this.onSaveClick();
    }

    /**
     * Display confirmation message if form is changed
     * @return boolean false - if no confirmation needed
     */
    ,confirmDiscardChanges: function(){
        if(!this._isDirty) {
            return false;
        }

        Ext.Msg.show({
            title:  L.Confirmation
            ,msg:   L.SavingChangedDataMessage
            ,icon:  Ext.Msg.QUESTION
            ,buttons: Ext.Msg.YESNOCANCEL
            ,scope: this
            ,fn: function(b, text, opt){
                switch(b){
                    case 'yes':
                        this.onSaveClick();
                        break;
                    case 'no':
                        this.clearContainers();
                        this._isDirty = false;
                        this.onCancelClick();
                        break;
                }
            }
        });

        return true;
    }

    ,callConfirmationCallback: function() {
        if (this.confirmationCallback) {
            this.confirmationCallback();

            return true;
        }

        return false;
    }

    ,onAfterConfirming: function() {
        if (this.confirmationCallback) {
            this.confirmationCallback();
        } else {
            this.fireEvent('actionconfirmed', this);
        }
    }
});
