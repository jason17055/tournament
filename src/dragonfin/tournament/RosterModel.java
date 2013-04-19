package dragonfin.tournament;

import java.util.*;
import javax.swing.table.*;

public class RosterModel extends AbstractTableModel
{
	Tournament tournament;
	static ResourceBundle strings = MainWindow.strings;

	public RosterModel()
	{
	}

	public void setTournament(Tournament newTournament)
	{
		this.tournament = newTournament;
		fireTableStructureChanged();
	}

	@Override
	public String getColumnName(int col) {
		if (col == 0) {
			return strings.getString("roster.name_header");
		}
		else {
			return null;
		}
	}

	@Override
	public int getRowCount()
	{
		return 0;
	}

	@Override
	public int getColumnCount()
	{
		return 1;
	}

	@Override
	public boolean isCellEditable(int row, int col)
	{
		return true;
	}

	@Override
	public Object getValueAt(int row, int col)
	{
		return "foo";
	}
}
