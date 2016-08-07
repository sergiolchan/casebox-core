Ext.namespace('CB');

Ext.define('CB.WindowManager', {
    extend: 'Ext.util.Observable'

    ,xtype: 'CBWindowManager'

    ,defaultConfig: {
        defaultMode: 'window' // view || window || popout
    }

    ,constructor: function(config){
        this.config = Ext.valueFrom(config, {});
        Ext.applyIf(this.config, this.defaultConfig);

        this.callParent(arguments);
    }
    /**
     * loads basic data for given object id and try to open its window if found
     * @param  int id
     * @return void
     */
    ,openObjectWindowById: function (id, options) {
        if(!Ext.isNumeric(id)) {
            return;
        }

        CB_Objects.getBasicInfoForId(
            id
            ,function(r, e) {
                if(!r || (r.success !== true)) {
                    Ext.Msg.alert(
                        L.Error
                        ,L.RecordIdNotFound.replace('{id}', '#' + r.id)
                    );
                    return;
                }
                this.openObjectWindow(Ext.apply(Ext.valueFrom(options, {}), r.data));
            }
            ,this
        );
    }

    /**
     * at least template should be defined in config
     */
    ,openObjectWindow: function(config) {
        if(Ext.isEmpty(config)) {
            return;
        }

        if(Ext.isEmpty(config.template_id)) {
            return Ext.Msg.alert(
                'Error opening object'
                ,'Template should be specified for object window to load.'
            );
        }

        //load target_id if its a link
        config.id = Ext.valueFrom(config.target_id, config.id);

        if (config.popOut === true) {
            return this.openObjectPopOutWindow(config);
        }

        var mode = Ext.valueFrom(config.mode, App.config.default_object_edit_mode)
            ,templateType = CB.DB.templates.getType(config.template_id);

        if (mode == 'view') {
            if(templateType == 'file') {
                return this.openFileEditView(config);
            } else {
                return this.openObjectEditView(config);
            }
        }

        //prepare and opening window
        var wndCfg = {
                xtype: (templateType === 'file'
                    ? 'CBFileEditWindow'
                    : 'CBObjectEditWindow'
                )
                ,data: config
                ,modal: Ext.valueFrom(config.modal, false)
            };

        Ext.copyTo(wndCfg, config, 'maximized,maximizable,minimizable,resizable,closable,border,bodyBorder,plain');
        wndCfg.minimizable = false;
        wndCfg.id = 'oew-' +
            (Ext.isEmpty(config.id)
                ? Ext.id()
                : config.id
            );

        var w = this.openWindow(wndCfg)
            ,winHeight = window.innerHeight;

        if(w) {
            var state = Ext.state.Manager.get(w.stateId);

            if(wndCfg.maximized || (state && state.maximized)) {
                w.maximize();
                return w;
            }

            if((winHeight > 0) && (w.getHeight() > winHeight)) {
                w.setHeight(winHeight - 20);
            }

            if(templateType === 'file') {
                w.center();

                if(config.name && (detectFileEditor(config.name) !== false)) {
                    w.maximize();
                }
            } else {
                if(config.alignWindowTo) {
                    this.alignWindowToCoords(w, config.alignWindowTo);

                } else if(!w.existing) {
                    this.alignWindowNext(w);
                }
            }

            delete w.existing;
        }

        return w;
    }

    ,openWindow: function(wndCfg) {
        var w = Ext.getCmp(wndCfg.id);

        if(w) {
            if (this.statusBar) {
                this.statusBar.setActiveButton(w.taskButton);
                this.statusBar.restoreWindow(w);
            }

            //set a flag that this was an existing window
            w.existing = true;

        } else {
            w = Ext.create(wndCfg);
            w.show();

            if (this.statusBar) {
                w.taskButton = this.statusBar.addTaskButton(w);
            }
        }

        return w;
    }

    ,alignWindowNext: function (w) {
        w.alignTo(App.mainViewPort.getEl(), 'br-br?');

        //get anchored position
        var pos = w.getXY();
        //move above status bar and a bit from right side
        pos[0] -= 15;
        pos[1] -= 5;

        //position to the left of an active window if any
        var x = pos[0];

        if (this.statusBar) {
            this.statusBar.windowBar.items.each(
                function(btn) {
                    if(btn.win && (btn.win != w) && btn.win.isVisible() && !btn.win.maximized && (btn.win.xtype !== 'CBSearchEditWindow')) {
                        var wx = btn.win.getX() - btn.win.el.getWidth() - 15;
                        if(x > wx) {
                            x = wx;
                        }
                    }
                }
                ,this
            );
        }

        if(x < 15) {
            x = 15;
        }
        pos[0] = x;

        w.setXY(pos);
    }

    ,alignWindowToCoords: function (win, coords) {
        var vpEl = App.mainViewPort.getEl();
        win.alignTo(vpEl, 'br-br?');

        //get anchored position
        var pos = win.getXY()
            ,w = win.getWidth()
            ,h = win.getHeight();

        //move above status bar and a bit from right side
        pos[0] -= 15;
        pos[1] -= 5;

        //position to center and below of given coords
        var x = pos[0];

        pos[0] = coords[0] - w / 2;
        pos[1] = coords[1] + 10;

        // check if window didnt go outside of viewport
        if (pos[0] + w > vpEl.getWidth()) {
            pos[0] = vpEl.getWidth() - w - 10;
        }

        if (pos[1] + h > vpEl.getHeight()) {
            pos[1] = vpEl.getHeight() - h - 20;
        }

        win.setXY(pos);
    }

    ,openObjectPopOutWindow: function(data) {
        var url = '/c/' + App.config.coreName + '/';

        url = url + 'edit/' + data.template_id;
        if(!Ext.isEmpty(data.id)) {
            url += '/' + data.id;
        }

        window.open(url, '_blank');
    }

    ,openObjectEditView: function(config) {
        var ev = App.explorer.objectEditView;

        ev.load(config);
        App.explorer.getLayout().setActiveItem(ev);

    }

    ,openFileEditView: function(config) {
        this.activeFileView = new CB.file.edit.View({
            closeAction: 'destroy'
            ,onClose: function() {
                this.close();
            }
            ,listeners: {
                scope: this
                ,beforeclose: function(panel, eOpts) {
                    App.mainViewPort.remove(panel); //, {destroy: true}
                    App.mainViewPort.items.each(function (i) {
                        i.show();
                    });
                    delete this.fileView;
                }
            }
        });

        App.mainViewPort.items.each(function (i) {
            i.hide();
        });

        App.mainViewPort.add(this.activeFileView);
        this.activeFileView.load(config);
    }
});
