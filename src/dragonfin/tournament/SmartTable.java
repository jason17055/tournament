package dragonfin.tournament;

import java.awt.Component;
import java.awt.event.*;
import java.util.*;
import javax.swing.*;
import javax.swing.table.*;

public class SmartTable extends JTable
{
	public SmartTable()
	{
	}

	public SmartTable(ResultSetModel m)
	{
		super(m);
		reloadModel(m);
	}

	public void setModel(TableModel tm)
	{
		super.setModel(tm);

		if (tm instanceof ResultSetModel) {
			reloadModel((ResultSetModel) tm);
		}
	}

	void reloadModel(ResultSetModel m)
	{
		for (int i = 0; i < m.getColumnCount(); i++)
		{
			updateCellEditor(m, i);
		}
	}

	void updateCellEditor(ResultSetModel m, int col)
	{
		if (m.isLookupColumn(col))
		{
			ResultSetModel.Lookup look = m.getLookupColumn(col);
			getColumnModel().getColumn(col).setCellEditor(
				new MyLookupCellEditor(m, col, look)
				);
		}
	}

	class MyLookupCellEditor extends AbstractCellEditor
		implements TableCellEditor
	{
		ResultSetModel.Lookup lookup;
		JComboBox<LookupItem> comboBox;
		Object unlistedValue;

		MyLookupCellEditor(ResultSetModel m, int col, ResultSetModel.Lookup lookup)
		{
			this.lookup = lookup;
		}

		//implements TableCellEditor
		public Component getTableCellEditorComponent(JTable table, Object value, boolean isSelected, int row, int column)
		{
			this.comboBox = new JComboBox<LookupItem>();
			this.comboBox.addItemListener(
				new ItemListener() {
					public void itemStateChanged(ItemEvent evt) {
						onItemStateChanged(evt);
					}
				});

			List<LookupItem> items = lookup.getLookupList();
			int found = -1;
			for (int i = 0; i < items.size(); i++) {
				LookupItem item = items.get(i);
				if (item.returnValue.equals(value)) {
					found = i;
				}
				comboBox.addItem(item);
			}

			if (found == -1) {
				found = comboBox.getItemCount();
				comboBox.addItem(
					new LookupItem(value)
					);
			}

			assert found != -1;
			comboBox.setSelectedIndex(found);

			if (lookup.showUnlistedOption()) {
				comboBox.addItem(LookupItem.UNLISTED);
			}

			return comboBox;
		}

		@Override
		public Object getCellEditorValue()
		{
			LookupItem item = (LookupItem)comboBox.getSelectedItem();
			if (item == LookupItem.UNLISTED) {
				return unlistedValue;
			}
			else {
				return item.returnValue;
			}
		}

		private void onItemStateChanged(ItemEvent evt)
		{
			if (evt.getStateChange() == ItemEvent.SELECTED &&
				evt.getItem() == LookupItem.UNLISTED)
			{
				unlistedValue = lookup.doUnlistedOption(comboBox);
			}
		}
	}
}
