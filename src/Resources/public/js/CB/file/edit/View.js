Ext.namespace('CB');

Ext.define('CB.file.edit.View', {
    extend: 'CB.object.edit.View'

    ,alias: 'CBFileEditView'

    ,xtype: 'CBFileEditView'
    ,previewZoom: 75

    ,initComponent: function() {
        this.callParent(arguments);

        this.on('openversion', this.onOpenVersionEvent, this);
    }

    /**
     * init this component actions
     * @return void
     */
    ,initActions: function() {
        this.callParent(arguments);

        Ext.apply(this.actions, {
            download: new Ext.Action({
                tooltip: L.Download
                ,iconCls: 'fa fa-download'
                ,scope: this
                ,handler: this.onDownloadClick
            })

            ,restoreVersion: new Ext.Action({
                tooltip: L.Restore
                ,iconCls: 'fa fa-mail-reply'
                ,hidden: true
                ,scope: this
                ,handler: this.onRestoreVersionClick
            })

            ,zoomOut: new Ext.Action({
                tooltip: L.ZoomOut
                ,iconCls: 'fa fa-search-minus'
                ,hidden: true
                ,itemId: 'zoomOut'
                ,scope: this
                ,handler: this.onZoomOutClick
            })

            ,zoomIn: new Ext.Action({
                tooltip: L.ZoomIn
                ,iconCls: 'fa fa-search-plus'
                ,hidden: true
                ,itemId: 'zoomIn'
                ,scope: this
                ,handler: this.onZoomInClick
            })

            ,webdavlink: new Ext.Action({
                text: L.WebDAVLink
                ,itemId: 'webdavlink'
                ,scope: this
                ,handler: this.onWebDAVLinkClick
            })
        });
    }

    /**
     * method that should return top toolbar buttons
     * @return array
     */
    ,getToolbarButtons: function() {
        //call parent to let it define other buttons required like follow
        this.callParent(arguments);

        // this.downloadSeparator = Ext.create({xtype: 'tbseparator'});
        this.zoomLabel = Ext.create({
            xtype: 'tbtext'
            ,hidden: true
            ,text: this.previewZoom  + ' %'
        });

        return [
            this.actions.back
            ,this.actions.edit
            ,this.actions.restoreVersion
            ,this.actions.save
            ,'->'
            ,this.actions.download
            ,this.actions.zoomOut
            ,this.zoomLabel
            ,this.actions.zoomIn
            ,new Ext.Button({
                text: L.More  + ' &hellip;'
                ,itemId: 'more'
                ,arrowVisible: false
                ,scale: 'medium'
                ,menu: [
                    this.actions.refresh
                    ,this.actions.webdavlink
                    ,this.actions.permalink
                    ,'-'
                    ,this.actions['delete']
                    ,this.actions.rename
                    ,'-'
                    ,this.actions.notifyOn
                    ,this.actions.notifyOff
                ]
            })
            ,'->'
            ,this.actions.showInfoPanel
            ,this.actions.popout
            ,this.actions.close
        ];
    }

    /**
     * initialize containers used
     * @return void
     */
    ,initContainerItems: function() {
        this.callParent(arguments);

        // add title for gird container in edit mode
        this.gridContainer.cls = 'obj-plugin';
        this.gridContainer.addDocked(
            [{
                xtype: 'toolbar'
                ,hidden: true
                ,border: false
                ,items: [{
                    xtype: 'label'
                    ,cls: 'title'
                    ,text: L.Metadata
                }]
            }]
            ,'top'
        );

        Ext.destroy(this.complexFieldContainer);

        this.titleView = new CB.object.TitleView({
            padding: 10
            ,floatingDetails: true
        });

        this.titleContainer = Ext.create({
            xtype: 'panel'
            ,border: false
            ,autoHeight: true
            ,items: [
                this.titleView
            ]
        });

        this.complexFieldContainer = Ext.create({
            xtype: 'form'
            ,border: false
            ,layout: 'fit'
            ,flex: 1
            ,api: {
                submit: CB_Objects.save
            }
            ,items: []
        });

        Ext.override(
            this.pluginsContainer
            ,{
                onLoadData: this.onPluginContainerLoadData
            }
        );
    }

    /**
     * function that should return items structure based on template config
     * @return array
     */
    ,getLayoutItems: function() {
        this.templateCfg.layout = 'horizontal';

        var rez = [
            {
                region: 'center'
                ,border: false
                ,scrollable: true
                ,layout: {
                    type: 'vbox'
                    ,align: 'stretch'
                }
                ,items: [
                    this.titleContainer
                    ,this.complexFieldContainer
                ]
            }, {
                region: 'east'
                ,itemId: 'infoPanel'
                ,hidden: true
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
                    ,this.gridContainer
                    ,this.pluginsContainer
                ]
            }
        ];

        return rez;
    }

    /**
     * loading preview panel of the file
     * @return void
     */
    ,processLoadPreviewData: function(r, e) {
        this.callParent(arguments);

        this.titleView.update(this.data);

        var scrollable = (getFileExtension(this.data.name) !== 'pdf');

        this.previewPanel = new CB.object.view.Preview({
            border: false
            ,scrollable: scrollable
            ,bodyStyle: 'padding: 0'
            ,listeners: {
                scope: this
                ,loaded: this.syncZoomingButtons
            }
        });

        this.complexFieldContainer.removeAll(true);
        this.complexFieldContainer.update('');
        this.complexFieldContainer.add(
            this.previewPanel
        );

        this.previewPanel.data = Ext.clone(this.data);
        this.previewPanel.loadPreview(this.data.id, this.loadedVersionId);
    }

    ,syncZoomingButtons: function(p) {
        var zoomer = p.getEl().query('.zoomer')
            ,canZoom = !Ext.isEmpty(zoomer);

        this.actions.zoomOut.setHidden(!canZoom);
        this.zoomLabel.setHidden(!canZoom);
        this.actions.zoomIn.setHidden(!canZoom);
        if (canZoom) {
            this.zoomer = Ext.get(zoomer[0]);

            this.zoomer.setStyle('margin', 'auto');
            this.updateZoomerWidth();
        }
    }

    ,onZoomOutClick: function(b, e) {
        this.previewZoom = Math.max(this.previewZoom - 5, 10);
        this.updateZoomerWidth();
    }

    ,onZoomInClick: function(b, e) {
        this.previewZoom = Math.min(this.previewZoom + 5, 100);
        this.updateZoomerWidth();
    }

    ,updateZoomerWidth: function() {
        this.zoomLabel.setText(this.previewZoom + '%');
        this.zoomer.setStyle('width', this.previewZoom + '%');
    }
    /**
     * method for processing server data on editing item
     * @return void
     */
    ,processLoadEditData: function(r, e) {
        this.gridContainer.getDockedComponent(0).setHidden(false);
        this.callParent(arguments);
        this.titleView.update(this.data);
        this.onShowInfoPanelClick();
    }

    ,updateComplexFieldContainer: function() {
        this.editType = detectFileEditor(this.data.name);

        switch(this.editType) {
            case 'text':
                this.contentEditor = new Jarvus.ace.Editor({
                    border: false
                    ,subscribe: {
                        'EditorSession': [
                            'change'
                        ]
                    }
                    ,listeners: {
                        scope: this
                        ,editorsessionchange: function(ed, session, eOpts) {
                            this.onChange();
                        }
                    }
                });

                var modelist = ace.require("ace/ext/modelist")
                    ,mode = modelist.getModeForPath(this.data.name).mode;

                this.contentEditor.getConfiguration().setOption('mode', mode);
                break;

            case 'html':
                this.contentEditor = new Ext.ux.HtmlEditor({
                    border: false
                    ,cls: 'editor-no-border'
                    ,listeners: {
                        scope: this
                        ,change: this.onEditorChangeEvent
                        ,sync: this.onEditorChangeEvent
                    }
                });
                break;
        }

        if(this.contentEditor) {
            this.complexFieldContainer.add(this.contentEditor);

            this.loadContent();
        }
    }

    ,loadContent: function() {
        CB_Files.getContent(this.data.id, this.onLoadContent, this);
    }

    ,onLoadContent: function(r, e) {
        if(!r || (r.success !== true)) {
            plog('Error loading file content ', this.data);
            return;
        }

        if(this.contentEditor) {
            var dirty = this.isDirty();
            this.contentEditor.setValue(r.data);
            this._isDirty = dirty;
        }
    }

    ,onPluginContainerLoadData: function(r, e) {
        var w = this.up('window');
        if(w && w.viewMode === 'edit') {
            delete r.data.meta;
        }

        this.callParent(arguments);
    }

    ,updateButtons: function() {
        this.editType = detectFileEditor(this.data.name);

        this.callParent(arguments);

        // this.downloadSeparator.setHidden(this.actions.back.isHidden());

        this.actions.edit.setHidden(
            (this.viewMode === 'edit') ||
            (this.editType === false) ||
            !Ext.isEmpty(this.loadedVersionId)
        );

        this.actions.webdavlink.setHidden(this.editType !== 'webdav');

        this.actions.save.setDisabled(false);

        this.actions.restoreVersion.setHidden(Ext.isEmpty(this.loadedVersionId));

        this.pluginsContainer.setSelectedVersion({
            id: this.data.id
            ,versionId: this.loadedVersionId
        });
    }

    /**
     * handler for edit toolbar button
     * @param  button b
     * @param  event e
     * @return void
     */
    ,onEditClick: function(b, e) {
        switch(this.editType) {
            case 'text':
            case 'html':
                this.viewMode = 'edit';
                this.doLoad();
                break;

            case 'webdav':

                App.openWebdavDocument(this.data);
                break;
        }
    }

    ,onSaveClick: function(b, e) {
        this.saveContent();

        if(!this._isDirty) {
            this.closeOnSaveContent = true;

        } else {
            this.readValues();

            this.getEl().mask(L.Saving + ' ...', 'x-mask-loading');

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
    }

    ,saveContent: function() {
        if(Ext.isEmpty(this.contentEditor)) {
            return;
        }

        this.getEl().mask(L.Processing + ' ...', 'x-mask-loading');

        CB_Files.saveContent(
            {
                id: this.data.id
                ,data: this.contentEditor.getValue()
            }
            ,this.processSaveContent
            ,this
        );
    }

    ,processSaveContent: function(r, e){
        this.getEl().unmask();
        this.actions.save.setDisabled(Ext.isEmpty(this.contentEditor));
        if(this.closeOnSaveContent) {
            this.close();
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

        var r = action.result;

        if(!r || (r.success !== true)) {
            App.showException(r);
        } else {
            this._isDirty = false;
            App.fireEvent('objectchanged', r.data, this);
            this.close();
        }
    }

    /**
     * event handler for content editors change
     * @param  component ed
     * @return void
     */
    ,onEditorChangeEvent: function(ed) {
        this.actions.save.setDisabled(false);
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
     * download button handler
     * @param  Button b
     * @param  EventObject e
     * @return void
     */
    ,onDownloadClick: function(b, e){
        App.downloadFile(this.data.id, false, this.loadedVersionId);
    }

    ,onOpenVersionEvent: function(data, pluginComponent) {
        this.loadedVersionId = data.id;

        if(!Ext.isEmpty(this.loadedVersionId)) {
            this.viewMode = 'preview';
        }

        this.doLoad();
    }

    ,onRestoreVersionClick: function(){
        if(Ext.isEmpty(this.loadedVersionId)) {
            return;
        }

        CB_Files.restoreVersion(
            this.loadedVersionId
            ,function(r, e){
                App.mainViewPort.fireEvent('fileuploaded', {data: r.data});

                delete this.loadedVersionId;

                this.doLoad();
            }
            ,this
        );
    }

    ,onWebDAVLinkClick: function(b, e) {
        App.openWebdavDocument(this.data ,false);
    }
});
